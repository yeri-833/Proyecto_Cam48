<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class TelegramSalidasSegurasTest extends TestCase
{
    private const string ChatId = '987654321';

    private const string BotToken = '123456789:BOT_CREDENTIAL_FICTICIA';

    private const string VincToken = 'VINC_abcdef0123456789abcdef0123456789abcdef0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => self::BotToken]);
        Http::preventStrayRequests();
        Log::spy();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            // Mockery puede fallar antes de que Laravel limpie sus manejadores.
            HandleExceptions::flushState($this);
        }
    }

    #[DataProvider('respuestasHttpFallidas')]
    public function test_sync_rechaza_respuestas_http_fallidas_sin_exponer_datos_ni_avanzar_offset(int $status): void
    {
        Cache::put('telegram_bot_offset', 17);
        Http::fake([
            'https://api.telegram.org/bot'.self::BotToken.'/getUpdates*' => Http::response([
                'ok' => false,
                'description' => self::ChatId.' '.self::BotToken.' '.self::VincToken,
            ], $status),
        ]);

        $this->artisan('telegram:sync')
            ->doesntExpectOutputToContain(self::ChatId)
            ->doesntExpectOutputToContain(self::BotToken)
            ->doesntExpectOutputToContain(self::VincToken)
            ->expectsOutputToContain('TELEGRAM_SYNC_FAILED')
            ->assertFailed();

        $this->assertSame(17, Cache::get('telegram_bot_offset'));
        $this->assertLogSeguro('TELEGRAM_SYNC_FAILED');
        Log::shouldHaveReceived('error')->once()->withArgs(
            static fn (string $mensaje, array $contexto): bool => ($contexto['http_status'] ?? null) === $status,
        );
        Http::assertSentCount(1);
    }

    /** @return array<string, array{int}> */
    public static function respuestasHttpFallidas(): array
    {
        return ['no autorizado' => [401], 'limite de solicitudes' => [429], 'error del proveedor' => [500]];
    }

    #[DataProvider('fallosSinteticos')]
    public function test_sync_no_expone_datos_de_excepciones_en_logs_ni_consola(string $tipo): void
    {
        Http::fake(function () use ($tipo) {
            throw $this->crearExcepcion($tipo);
        });

        $this->artisan('telegram:sync')
            ->doesntExpectOutputToContain(self::ChatId)
            ->doesntExpectOutputToContain(self::BotToken)
            ->doesntExpectOutputToContain(self::VincToken)
            ->doesntExpectOutputToContain('PERSONA_FICTICIA_SQL')
            ->expectsOutputToContain('TELEGRAM_SYNC_FAILED')
            ->assertFailed();

        $this->assertLogSeguro('TELEGRAM_SYNC_FAILED');
    }

    #[DataProvider('fallosSinteticos')]
    public function test_servicio_telegram_no_expone_excepciones_del_proveedor(string $tipo): void
    {
        Http::fake(function () use ($tipo) {
            throw $this->crearExcepcion($tipo);
        });

        $resultado = (new TelegramService)->enviarMensajeDetallado(self::ChatId, 'Mensaje ficticio');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Fallido', $resultado['status']);
        $this->assertLogSeguro('TELEGRAM_SEND_FAILED');
    }

    #[DataProvider('fallosSinteticos')]
    public function test_endpoint_sync_no_expone_excepciones_en_http_ni_logs(string $tipo): void
    {
        $admin = User::factory()->create(['role' => 'Director']);
        Artisan::shouldReceive('call')->once()->with('telegram:sync')->andThrow($this->crearExcepcion($tipo));

        $response = $this->actingAs($admin)->getJson(route('telegram.sync'));

        $response->assertStatus(500)->assertJsonPath('code', 'TELEGRAM_SYNC_FAILED');
        $this->assertTextoSeguro($response->getContent());
        $this->assertLogSeguro('TELEGRAM_SYNC_FAILED');
    }

    #[DataProvider('codigosDeSalida')]
    public function test_endpoint_no_reenvia_salida_arbitraria_del_comando(int $exitCode): void
    {
        $admin = User::factory()->create(['role' => 'Director']);
        Artisan::shouldReceive('call')->once()->with('telegram:sync')->andReturn($exitCode);
        Artisan::shouldReceive('output')->andReturn(self::ChatId.' '.self::BotToken.' '.self::VincToken.' PERSONA_FICTICIA_SQL');

        $response = $this->actingAs($admin)->getJson(route('telegram.sync'));

        $response->assertOk()->assertJsonPath('status', $exitCode === 0 ? 'ok' : 'error');
        $this->assertTextoSeguro($response->getContent());
    }

    public function test_sync_sanea_errores_de_cache_anteriores_a_la_lectura_de_mensajes(): void
    {
        Http::fake();
        $realCacheManager = Cache::getFacadeRoot();
        Cache::shouldReceive('driver')->andReturnUsing(
            static fn (?string $store = null): Repository => $realCacheManager->driver($store),
        );
        Cache::shouldReceive('get')->once()->with('telegram_bot_offset', 0)->andThrow($this->crearExcepcion('conexion'));

        $this->artisan('telegram:sync')
            ->doesntExpectOutputToContain(self::BotToken)
            ->expectsOutputToContain('TELEGRAM_SYNC_FAILED')
            ->assertFailed();

        $this->assertLogSeguro('TELEGRAM_SYNC_FAILED');
        Http::assertNothingSent();
    }

    public function test_endpoint_sanea_errores_al_adquirir_el_bloqueo(): void
    {
        $admin = User::factory()->create(['role' => 'Director']);
        $realCacheManager = Cache::getFacadeRoot();
        Cache::shouldReceive('driver')->andReturnUsing(
            static fn (?string $store = null): Repository => $realCacheManager->driver($store),
        );
        Cache::shouldReceive('lock')->once()->with('telegram_sync_lock', 10)->andThrow($this->crearExcepcion('sql'));

        $response = $this->actingAs($admin)->getJson(route('telegram.sync'));

        $response->assertStatus(500)->assertJsonPath('code', 'TELEGRAM_SYNC_FAILED');
        $this->assertTextoSeguro($response->getContent());
        $this->assertLogSeguro('TELEGRAM_SYNC_FAILED');
    }

    /** @return array<string, array{string}> */
    public static function fallosSinteticos(): array
    {
        return [
            'conexion' => ['conexion'],
            'consulta SQL' => ['sql'],
            'error de tipo' => ['error'],
        ];
    }

    /** @return array<string, array{int}> */
    public static function codigosDeSalida(): array
    {
        return ['exito' => [0], 'fallo' => [1]];
    }

    private function crearExcepcion(string $tipo): Throwable
    {
        $mensaje = 'https://api.telegram.org/bot'.self::BotToken.'/sendMessage '.self::ChatId.' '.self::VincToken.' PERSONA_FICTICIA_SQL';

        return match ($tipo) {
            'sql' => new QueryException('sqlite', 'update personal set telegram_chat_id = ?, nombre_completo = ?', [self::ChatId, 'PERSONA_FICTICIA_SQL'], new PDOException($mensaje)),
            'error' => new \TypeError($mensaje),
            default => new ConnectionException($mensaje, 0, new RuntimeException($mensaje)),
        };
    }

    private function assertLogSeguro(string $codigo): void
    {
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $mensaje, array $contexto) use ($codigo): bool {
            $this->assertStringContainsString($codigo, $mensaje);
            $this->assertTextoSeguro($mensaje.' '.json_encode($contexto));

            return true;
        });
    }

    private function assertTextoSeguro(string $texto): void
    {
        foreach ([self::ChatId, self::BotToken, self::VincToken, 'PERSONA_FICTICIA_SQL', 'update personal'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $texto);
        }
    }
}
