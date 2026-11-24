<?php

namespace Tests\Feature;

use App\Jobs\NotificarAsistenciaJob;
use App\Jobs\NotificarAvisoPersonalJob;
use App\Jobs\NotificarEventoTutorJob;
use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Tutor;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class NotificacionesResilienciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
    }

    /**
     * WhatsApp responde HTTP 500 sin confirmar la aceptación del envío.
     */
    public function test_whatsapp_responde_500_con_resultado_incierto(): void
    {
        Config::set('whatsapp.api_url', 'https://fake-gateway.cam48.test');
        Config::set('whatsapp.api_token', 'test_token_123');

        Http::fake([
            'https://fake-gateway.cam48.test/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $service = new WhatsAppService;
        $resultado = $service->enviarMensajeDirectoDetallado('5512345678', 'Aviso de prueba');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Incierto', $resultado['status']);
    }

    /**
     * Telegram responde HTTP 429 (Rate Limit / Too Many Requests).
     */
    public function test_telegram_responde_429_rate_limit_retorna_false(): void
    {
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 429,
                'description' => 'Too Many Requests: retry after 30',
            ], 429),
        ]);

        $service = new TelegramService;
        $resultado = $service->enviarMensajeDetallado('987654321', 'Aviso de prueba');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Fallido', $resultado['status']);
    }

    /**
     * Telegram responde HTTP 403 (Bot bloqueado por el usuario - Error definitivo).
     */
    public function test_telegram_responde_403_bot_bloqueado_error_definitivo(): void
    {
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 403,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 403),
        ]);

        $service = new TelegramService;
        $resultado = $service->enviarMensajeDetallado('987654321', 'Aviso de prueba');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Fallido', $resultado['status']);
    }

    /**
     * Error de conexión de red (DNS / Host inalcanzable).
     */
    public function test_error_de_conexion_en_servicios_es_capturado_sin_detener_el_proceso(): void
    {
        Config::set('whatsapp.api_url', 'https://fake-gateway.cam48.test');
        Config::set('whatsapp.api_token', 'test_token_123');
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        Http::fake(function () {
            throw new ConnectionException('Could not resolve host: network down');
        });

        $wsService = new WhatsAppService;
        $wsResult = $wsService->enviarMensajeDirectoDetallado('5512345678', 'Test error red');
        $this->assertFalse($wsResult['success']);
        $this->assertSame('Incierto', $wsResult['status']);

        $tgService = new TelegramService;
        $tgResult = $tgService->enviarMensajeDetallado('987654321', 'Test error red');
        $this->assertFalse($tgResult['success']);
        $this->assertSame('Incierto', $tgResult['status']);
    }

    /**
     * Timeout de conexión (Socket timeout - Entrega desconocida).
     */
    public function test_timeout_de_conexion_retorna_incierto_de_forma_controlada(): void
    {
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds');
        });

        $tgService = new TelegramService;
        $resultado = $tgService->enviarMensajeDetallado('987654321', 'Test timeout');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Incierto', $resultado['status']);
    }

    public function test_http_exitoso_con_rechazo_explicito_no_confirma_envio(): void
    {
        config([
            'whatsapp.api_url' => 'https://fake-gateway.cam48.test',
            'whatsapp.api_token' => 'TOKEN_SINTETICO_WS',
            'telegram.bot_token' => 'TOKEN_SINTETICO_TG',
        ]);
        Http::fake([
            'https://fake-gateway.cam48.test/*' => Http::response(['sent' => false]),
            'https://api.telegram.org/*' => Http::response(['ok' => false]),
        ]);

        $this->assertSame('Fallido', (new WhatsAppService)->enviarMensajeDirectoDetallado('5512345678', 'Aviso')['status']);
        $this->assertSame('Fallido', (new TelegramService)->enviarMensajeDetallado('900003', 'Aviso')['status']);
    }

    #[DataProvider('trabajosConPerdidaDeConexion')]
    public function test_trabajo_persiste_incertidumbre_y_omite_el_reenvio(string $tipo): void
    {
        config([
            'whatsapp.api_url' => 'https://fake-gateway.cam48.test',
            'whatsapp.api_token' => 'TOKEN_SINTETICO_WS',
            'telegram.bot_token' => 'TOKEN_SINTETICO_TG',
        ]);
        Http::preventStrayRequests();
        $intentos = ['whatsapp' => 0, 'telegram' => 0];
        Http::fake(function (Request $solicitud) use (&$intentos) {
            $canal = str_contains($solicitud->url(), 'telegram.org') ? 'telegram' : 'whatsapp';
            $intentos[$canal]++;

            throw new ConnectionException('La conexión se perdió. TOKEN_SINTETICO_CONTACTO 5550000000');
        });

        if ($tipo === 'personal') {
            $personal = Personal::create([
                'id' => 'DOC_TRANSPORTE',
                'nombre_completo' => 'Docente de prueba',
                'telegram_chat_id' => '900003',
                'activo' => true,
            ]);
            $job = new NotificarAvisoPersonalJob($personal, 'Aviso', 'aud-h04-personal');
        } else {
            $alumno = Alumno::create([
                'matricula' => 'TRANSPORTE01',
                'nombre' => 'Alumno',
                'apellido' => 'Ficticio',
                'activo' => true,
            ]);
            $tutor = Tutor::create([
                'matricula_alumno' => $alumno->matricula,
                't1_nombre' => 'Tutor de prueba',
                't1_telefono' => '5512345678',
                't1_telegram_chat_id' => '900003',
            ]);
            $job = $tipo === 'asistencia'
                ? new NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Alumno ficticio')
                : new NotificarEventoTutorJob($tutor, 'Convocatoria', 'Junta de prueba', '2026-11-19', 'Plantel', 101);
        }

        $clave = $job instanceof NotificarAvisoPersonalJob ? $job->idempotencyKey : $job->resolverIdempotencyKey();
        $payload = serialize($job);
        $this->app->call([unserialize($payload), 'handle']);
        $this->app->call([unserialize($payload), 'handle']);
        unserialize($payload)->failed(new ConnectionException('TOKEN_SINTETICO_CONTACTO 5550000000'));

        $this->assertSame(['whatsapp' => $tipo === 'personal' ? 0 : 1, 'telegram' => 1], $intentos);
        $registro = DB::table('notificaciones_procesadas')->where('idempotency_key', $clave)->first();
        $this->assertNotNull($registro);
        $this->assertSame('INCIERTO', $registro->estado);
        $this->assertSame(1, (int) $registro->intentos);
        $this->assertNull($registro->fecha_entrega);
        $this->assertNull($registro->lease_expires_at);
        $canales = json_decode($registro->detalle_canales, true);
        $this->assertSame('INCIERTO', $canales['telegram']['estado']);
        if ($tipo !== 'personal') {
            $this->assertSame('INCIERTO', $canales['whatsapp']['estado']);
        }
        $this->assertStringNotContainsString('TOKEN_SINTETICO_CONTACTO', $registro->detalle_canales.' '.$registro->ultimo_error);
        $this->assertStringNotContainsString('5550000000', $registro->detalle_canales.' '.$registro->ultimo_error);
    }

    /** @return array<string, array{string}> */
    public static function trabajosConPerdidaDeConexion(): array
    {
        return [
            'asistencia' => ['asistencia'],
            'aviso a personal' => ['personal'],
            'convocatoria a tutor' => ['evento'],
        ];
    }

    #[DataProvider('esquemasDeNotificaciones')]
    public function test_rechazo_en_otro_canal_no_reenvia_el_canal_incierto(bool $tieneDetalle): void
    {
        if ($tieneDetalle === false) {
            Schema::table('notificaciones_procesadas', function (Blueprint $table): void {
                $table->dropColumn('detalle_canales');
            });
        }

        config([
            'whatsapp.api_url' => 'https://fake-gateway.cam48.test',
            'whatsapp.api_token' => 'TOKEN_SINTETICO_WS',
            'telegram.bot_token' => 'TOKEN_SINTETICO_TG',
        ]);
        Http::preventStrayRequests();
        $intentosWhatsApp = 0;
        $intentosTelegram = 0;
        Http::fake([
            'https://fake-gateway.cam48.test/*' => function () use (&$intentosWhatsApp) {
                $intentosWhatsApp++;

                throw new ConnectionException('La conexión se perdió.');
            },
            'https://api.telegram.org/*' => function () use (&$intentosTelegram) {
                $intentosTelegram++;

                return Http::response(['ok' => false], 429);
            },
        ]);

        $alumno = Alumno::create([
            'matricula' => 'TRANSPORTE02',
            'nombre' => 'Alumno',
            'apellido' => 'Ficticio',
            'activo' => true,
        ]);
        Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'Tutor de prueba',
            't1_telefono' => '5512345678',
            't1_telegram_chat_id' => '900003',
        ]);
        $job = new NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Alumno ficticio');
        $reintentosSolicitados = 0;

        for ($intento = 0; $intento < 2; $intento++) {
            try {
                $job->handle(new WhatsAppService, new TelegramService);
            } catch (RuntimeException) {
                $reintentosSolicitados++;
            }
        }
        $job->failed(new RuntimeException('Reintentos agotados.'));

        $this->assertSame(1, $intentosWhatsApp);
        $this->assertSame($tieneDetalle ? 2 : 0, $reintentosSolicitados);
        $this->assertSame($tieneDetalle ? 2 : 1, $intentosTelegram);
        $registro = DB::table('notificaciones_procesadas')->where('idempotency_key', $job->resolverIdempotencyKey())->first();
        $this->assertNotNull($registro);
        $this->assertSame($tieneDetalle ? 'PARCIAL' : 'INCIERTO', $registro->estado);
        if ($tieneDetalle) {
            $canales = json_decode($registro->detalle_canales, true);
            $this->assertSame('INCIERTO', $canales['whatsapp']['estado']);
            $this->assertSame('FALLIDO', $canales['telegram']['estado']);
        }
    }

    /** @return array<string, array{bool}> */
    public static function esquemasDeNotificaciones(): array
    {
        return ['con detalle de canales' => [true], 'anterior al detalle de canales' => [false]];
    }

    /**
     * Modo simulación cuando no hay credenciales configuradas (entorno dev/local).
     */
    public function test_modo_simulacion_cuando_no_hay_credenciales_configuradas(): void
    {
        Config::set('whatsapp.api_url', '');
        Config::set('whatsapp.api_token', '');
        Config::set('telegram.bot_token', '');

        Http::fake();

        $wsService = new WhatsAppService;
        $wsResult = $wsService->enviarMensajeDirectoDetallado('5512345678', 'Test simulacion');
        $this->assertSame('Simulado', $wsResult['status']);
        $this->assertFalse($wsResult['success']);

        $tgService = new TelegramService;
        $tgResult = $tgService->enviarMensajeDetallado('987654321', 'Test simulacion');
        $this->assertSame('Simulado', $tgResult['status']);
        $this->assertFalse($tgResult['success']);

        Http::assertNothingSent();
    }

    /**
     * Escenario mixto: WhatsApp tiene éxito (HTTP 200) y Telegram rechaza (HTTP 429).
     */
    public function test_un_canal_tiene_exito_y_otro_falla_en_notificar_asistencia_job(): void
    {
        Config::set('whatsapp.api_url', 'https://fake-gateway.cam48.test');
        Config::set('whatsapp.api_token', 'test_token_123');
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        Http::fake([
            'https://fake-gateway.cam48.test/*' => Http::response(['success' => true], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => false], 429),
        ]);

        $alumno = Alumno::create([
            'matricula' => 'TESTRESIL01',
            'nombre' => 'Alumno',
            'apellido' => 'Resiliente',
            'activo' => true,
        ]);

        $tutor = Tutor::create([
            'matricula_alumno' => 'TESTRESIL01',
            't1_nombre' => 'Tutor',
            't1_apellido' => 'Test',
            't1_telefono' => '5511223344',
            't1_telegram_chat_id' => '123456789',
        ]);

        $job = new NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Alumno Resiliente');
        try {
            $job->handle(new WhatsAppService, new TelegramService);
        } catch (RuntimeException $e) {
            // Esperado: entrega parcial lanza excepción para forzar reintento en cola de Laravel
        }

        // WhatsApp fue enviado con éxito
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fake-gateway.cam48.test');
        });

        // Telegram fue intentado
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org');
        });
    }

    /**
     * Verificación de que la idempotencia duradera previene la repetición de mensajes en canales previos ante reintentos.
     */
    public function test_demostracion_de_reintento_sin_deduplicacion_repite_mensajes_en_canales_previos(): void
    {
        Config::set('whatsapp.api_url', 'https://fake-gateway.cam48.test');
        Config::set('whatsapp.api_token', 'test_token_123');
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        $whatsappCalls = 0;
        Http::fake([
            'https://fake-gateway.cam48.test/*' => function () use (&$whatsappCalls) {
                $whatsappCalls++;

                return Http::response(['success' => true], 200);
            },
            'https://api.telegram.org/*' => Http::response(['ok' => false], 429),
        ]);

        $alumno = Alumno::create([
            'matricula' => 'TESTDUP01',
            'nombre' => 'Alumno',
            'apellido' => 'Duplicado',
            'activo' => true,
        ]);

        Tutor::create([
            'matricula_alumno' => 'TESTDUP01',
            't1_nombre' => 'Tutor',
            't1_apellido' => 'Dup',
            't1_telefono' => '5599887766',
            't1_telegram_chat_id' => '998877665',
        ]);

        $job = new NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Alumno Duplicado');

        // Intento 1: WhatsApp tiene éxito, Telegram falla
        try {
            $job->handle(new WhatsAppService, new TelegramService);
        } catch (RuntimeException $e) {
            // Esperado: entrega parcial fuerza reintento
        }
        $this->assertSame(1, $whatsappCalls, 'WhatsApp debe haberse ejecutado 1 vez en el intento 1.');

        // Simulación de reintento del worker (Intento 2):
        // Con la tabla notificaciones_procesadas e idempotencia duradera, WhatsApp no se repite
        try {
            $job->handle(new WhatsAppService, new TelegramService);
        } catch (RuntimeException $e) {
            // Esperado: Telegram sigue fallando
        }
        $this->assertSame(1, $whatsappCalls, 'Idempotencia confirmada: En el intento 2, WhatsApp no se ejecutó nuevamente.');
    }
}
