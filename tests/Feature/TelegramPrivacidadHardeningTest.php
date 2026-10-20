<?php

namespace Tests\Feature;

use App\Models\Tutor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelegramPrivacidadHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('telegram.bot_token', '123456789:ABCdefGHIjklMNOpqrsTUVwxyz');
    }

    /**
     * Verifica que los Chat IDs completos NO se registran en los logs operativos al bloquear IDs secuenciales.
     */
    public function test_chat_id_completo_ausente_de_logs_en_bloqueo_de_vinculacion_secuencial(): void
    {
        $capturedContext = [];
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use (&$capturedContext) {
                $capturedContext = $context;

                return str_contains($message, 'identificador secuencial inseguro');
            });

        Http::fake([
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 101,
                        'message' => [
                            'message_id' => 1,
                            'chat' => ['id' => 987654321],
                            'text' => '/start tutor_1',
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/bot*/sendMessage*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        $this->assertArrayHasKey('chat_id', $capturedContext);
        $this->assertSame('987***21', $capturedContext['chat_id'], 'El Chat ID debe estar enmascarado.');
        $this->assertStringNotContainsString('987654321', (string) $capturedContext['chat_id']);
    }

    /**
     * Verifica que un token hexadecimal de 48 caracteres es aceptado y procesado.
     */
    public function test_token_hexadecimal_de_48_caracteres_es_aceptado_correctamente(): void
    {
        if (! Schema::hasTable('telegram_vinculaciones')) {
            Schema::create('telegram_vinculaciones', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 64)->unique();
                $table->string('vinculable_type');
                $table->unsignedBigInteger('vinculable_id');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->string('chat_id_registrado')->nullable();
                $table->timestamps();
            });
        }

        $rawToken = bin2hex(random_bytes(24)); // 48 caracteres hex válidos
        $this->assertSame(48, strlen($rawToken));

        $tutor = Tutor::create([
            'matricula_alumno' => 'MAT_TEL_01',
            't1_nombre' => 'Tutor Hex',
            't1_telefono' => '5511223344',
        ]);

        DB::table('telegram_vinculaciones')->insert([
            'token_hash' => hash('sha256', $rawToken),
            'vinculable_type' => Tutor::class,
            'vinculable_id' => $tutor->id,
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 201,
                        'message' => [
                            'message_id' => 2,
                            'chat' => ['id' => 555666777],
                            'text' => "/start VINC_{$rawToken}",
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/bot*/sendMessage*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        $tutor->refresh();
        $this->assertSame('555666777', (string) $tutor->t1_telegram_chat_id);
    }

    /**
     * Verifica que tokens con caracteres no hexadecimales (ej. guion bajo, letras g-z) son rechazados.
     */
    public function test_token_con_caracteres_no_hexadecimales_es_rechazado(): void
    {
        // 48 caracteres pero termina en caracteres no hex 'zzzz'
        $invalidToken = str_repeat('a', 44).'zzzz';
        $this->assertSame(48, strlen($invalidToken));

        Http::fake([
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 301,
                        'message' => [
                            'message_id' => 3,
                            'chat' => ['id' => 112233445],
                            'text' => "/start VINC_{$invalidToken}",
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/bot*/sendMessage*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        // No se debe haber enviado ningún mensaje de vinculación exitosa ni de error VINC
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'sendMessage');
        });
    }

    /**
     * Token vencido es rechazado.
     */
    public function test_token_vencido_es_rechazado(): void
    {
        if (! Schema::hasTable('telegram_vinculaciones')) {
            Schema::create('telegram_vinculaciones', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 64)->unique();
                $table->string('vinculable_type');
                $table->unsignedBigInteger('vinculable_id');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->string('chat_id_registrado')->nullable();
                $table->timestamps();
            });
        }

        $rawToken = bin2hex(random_bytes(24));

        DB::table('telegram_vinculaciones')->insert([
            'token_hash' => hash('sha256', $rawToken),
            'vinculable_type' => Tutor::class,
            'vinculable_id' => 999,
            'expires_at' => now()->subMinutes(5), // Ya expiró
            'created_at' => now()->subMinutes(35),
            'updated_at' => now()->subMinutes(35),
        ]);

        Http::fake([
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 401,
                        'message' => [
                            'message_id' => 4,
                            'chat' => ['id' => 333444555],
                            'text' => "/start VINC_{$rawToken}",
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/bot*/sendMessage*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        // Debe avisar que el enlace es inválido o expiró
        Http::assertSent(function ($request) {
            return str_contains($request['text'] ?? '', 'inválido, ya fue utilizado o ha expirado');
        });
    }

    /**
     * Ausencia de tabla telegram_vinculaciones tiene comportamiento seguro (fail-safe).
     */
    public function test_ausencia_de_tabla_telegram_vinculaciones_es_manejada_con_seguridad(): void
    {
        Schema::dropIfExists('telegram_vinculaciones');

        $rawToken = bin2hex(random_bytes(24));

        Http::fake([
            'https://api.telegram.org/bot*/getUpdates*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 501,
                        'message' => [
                            'message_id' => 5,
                            'chat' => ['id' => 777888999],
                            'text' => "/start VINC_{$rawToken}",
                        ],
                    ],
                ],
            ]),
            'https://api.telegram.org/bot*/sendMessage*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('telegram:sync')->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request['text'] ?? '', 'preparación institucional');
        });
    }

    /**
     * Excepciones de red o HTTP nunca exponen el bot token en el log de errores.
     */
    public function test_excepcion_en_sync_no_expone_bot_token_en_los_logs(): void
    {
        $botToken = '123456789:ABCdefGHIjklMNOpqrsTUVwxyz';
        Config::set('telegram.bot_token', $botToken);

        $loggedMessage = '';
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($msg) use (&$loggedMessage) {
                $loggedMessage = $msg;

                return true;
            });

        Http::fake(function () use ($botToken) {
            throw new \Exception("cURL error 7: Failed to connect to api.telegram.org/bot{$botToken}/getUpdates");
        });

        $this->artisan('telegram:sync')->assertFailed();

        $this->assertStringNotContainsString($botToken, $loggedMessage);
        $this->assertStringContainsString('TELEGRAM_SYNC_FAILED', $loggedMessage);
    }
}
