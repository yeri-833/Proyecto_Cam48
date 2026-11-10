<?php

namespace Tests\Feature;

use App\Jobs\NotificarAsistenciaJob;
use App\Models\Alumno;
use App\Models\Tutor;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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
     * WhatsApp responde HTTP 500 (Error del proveedor externo).
     */
    public function test_whatsapp_responde_500_retorna_false_sin_lanzar_excepcion_no_controlada(): void
    {
        Config::set('whatsapp.api_url', 'https://fake-gateway.cam48.test');
        Config::set('whatsapp.api_token', 'test_token_123');

        Http::fake([
            'https://fake-gateway.cam48.test/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $service = new WhatsAppService;
        $resultado = $service->enviarMensajeDirectoDetallado('5512345678', 'Aviso de prueba');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Fallido', $resultado['status']);
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
        $this->assertSame('Fallido', $wsResult['status']);

        $tgService = new TelegramService;
        $tgResult = $tgService->enviarMensajeDetallado('987654321', 'Test error red');
        $this->assertFalse($tgResult['success']);
        $this->assertSame('Fallido', $tgResult['status']);
    }

    /**
     * Timeout de conexión (Socket timeout - Entrega desconocida).
     */
    public function test_timeout_de_conexion_retorna_fallido_de_forma_controlada(): void
    {
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds');
        });

        $tgService = new TelegramService;
        $resultado = $tgService->enviarMensajeDetallado('987654321', 'Test timeout');

        $this->assertFalse($resultado['success']);
        $this->assertSame('Fallido', $resultado['status']);
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
     * Escenario mixto: WhatsApp tiene éxito (HTTP 200) y Telegram falla (HTTP 500).
     */
    public function test_un_canal_tiene_exito_y_otro_falla_en_notificar_asistencia_job(): void
    {
        Config::set('whatsapp.api_url', 'https://fake-gateway.cam48.test');
        Config::set('whatsapp.api_token', 'test_token_123');
        Config::set('telegram.bot_token', 'fake_telegram_token_123');

        Http::fake([
            'https://fake-gateway.cam48.test/*' => Http::response(['success' => true], 200),
            'https://api.telegram.org/*' => Http::response(['error' => 'Internal error'], 500),
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
        $job->handle(new WhatsAppService, new TelegramService);

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
            'https://api.telegram.org/*' => Http::response(['error' => 'Server Error'], 500),
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
        $job->handle(new WhatsAppService, new TelegramService);
        $this->assertSame(1, $whatsappCalls, 'WhatsApp debe haberse ejecutado 1 vez en el intento 1.');

        // Simulación de reintento del worker (Intento 2):
        // Con la tabla notificaciones_procesadas e idempotencia duradera, WhatsApp no se repite
        $job->handle(new WhatsAppService, new TelegramService);
        $this->assertSame(1, $whatsappCalls, 'Idempotencia confirmada: En el intento 2, WhatsApp no se ejecutó nuevamente.');
    }
}
