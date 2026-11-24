<?php

namespace Tests\Feature;

use App\Jobs\NotificarAvisoPersonalJob;
use App\Models\Personal;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class NotificacionesPersonalAsincronasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_envio_masivo_encola_jobs_asincronos_sin_bloquear_transporte(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'Director']);
        Personal::create([
            'id' => 'DOC_101',
            'nombre_completo' => 'Docente Uno',
            'telegram_chat_id' => 'CHAT_101',
            'activo' => 1,
        ]);
        Personal::create([
            'id' => 'DOC_102',
            'nombre_completo' => 'Docente Dos',
            'telegram_chat_id' => 'CHAT_102',
            'activo' => 1,
        ]);
        Personal::create([
            'id' => 'DOC_103',
            'nombre_completo' => 'Docente Sin Telegram',
            'telegram_chat_id' => null,
            'activo' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('personal.mensajeMasivo'), [
            'mensaje' => 'Reunión general de consejo técnico.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(NotificarAvisoPersonalJob::class, 2);
    }

    public function test_envio_masivo_requiere_autorizacion(): void
    {
        Queue::fake();

        $alumnoUser = User::factory()->create(['role' => 'alumno']);

        $response = $this->actingAs($alumnoUser)->post(route('personal.mensajeMasivo'), [
            'mensaje' => 'Intento no autorizado.',
        ]);

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_envios_masivos_entregan_una_vez_por_destinatario_y_lote(): void
    {
        Queue::fake();
        $this->freezeTime();
        config(['telegram.bot_token' => 'TOKEN_SINTETICO']);
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);

        $director = User::factory()->create(['role' => 'Director']);
        foreach (['DOC_MASIVO_1' => '900001', 'DOC_MASIVO_2' => '900002'] as $personalId => $chatId) {
            Personal::create([
                'id' => $personalId,
                'nombre_completo' => 'Docente de prueba '.$personalId,
                'telegram_chat_id' => $chatId,
                'activo' => true,
            ]);
        }

        $telegram = new TelegramService;
        for ($lote = 0; $lote < 2; $lote++) {
            $this->actingAs($director)->post(route('personal.mensajeMasivo'), [
                'mensaje' => 'El mismo aviso de prueba.',
            ])->assertRedirect()->assertSessionHas('success');

            foreach (Queue::pushed(NotificarAvisoPersonalJob::class)->slice($lote * 2) as $job) {
                $payload = serialize($job);
                unserialize($payload)->handle($telegram);
                unserialize($payload)->handle($telegram);
            }

            Http::assertSentCount(($lote + 1) * 2);
        }

        $chats = Http::recorded()->map(fn (array $intercambio): string => (string) $intercambio[0]['chat_id']);
        $this->assertSame(['900001' => 2, '900002' => 2], $chats->countBy()->sortKeys()->all());
        $this->assertDatabaseCount('notificaciones_procesadas', 4);
    }

    public function test_job_notificar_aviso_personal_aplica_idempotencia_durable_en_base_de_datos(): void
    {
        $personal = Personal::create([
            'id' => 'DOC_104',
            'nombre_completo' => 'Docente Ficticio',
            'telegram_chat_id' => 'CHAT_104',
            'activo' => 1,
        ]);

        $telegramMock = Mockery::mock(TelegramService::class);
        $telegramMock->shouldReceive('enviarMensajeDirectoDetallado')
            ->once()
            ->with('CHAT_104', 'DOCENTE FICTICIO', Mockery::pattern('/Aviso de prueba/'))
            ->andReturn(['status' => 'Enviado a proveedor', 'success' => true]);

        $idempotencyKey = 'test_key_'.uniqid();
        $job = new NotificarAvisoPersonalJob($personal, 'Aviso de prueba', $idempotencyKey);

        // Primer procesamiento: debe ejecutar el envío y persistir en BD
        $job->handle($telegramMock);

        // Segundo procesamiento idéntico: debe omitirse sin llamar de nuevo a Telegram
        $job->handle($telegramMock);

        $registro = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($registro, 'Debe existir registro en notificaciones_procesadas');
        $this->assertSame('ACEPTADO_PROVEEDOR', $registro->estado);
        $this->assertNotNull($registro->fecha_entrega);
    }

    public function test_job_libera_bloqueo_y_permite_reintento_ante_fallo_temporal(): void
    {
        $personal = Personal::create([
            'id' => 'DOC_105',
            'nombre_completo' => 'Docente Con Reintento',
            'telegram_chat_id' => 'CHAT_105',
            'activo' => 1,
        ]);

        $telegramMock = Mockery::mock(TelegramService::class);
        $telegramMock->shouldReceive('enviarMensajeDirectoDetallado')
            ->once()
            ->with('CHAT_105', 'DOCENTE CON REINTENTO', Mockery::pattern('/Mensaje con fallo inicial/'))
            ->andReturn(['status' => 'Fallido', 'success' => false]);

        $idempotencyKey = 'retry_key_'.uniqid();
        $job = new NotificarAvisoPersonalJob($personal, 'Mensaje con fallo inicial', $idempotencyKey);

        $falloCapturado = false;
        try {
            $job->handle($telegramMock);
        } catch (\RuntimeException $e) {
            $falloCapturado = true;
        }

        $this->assertTrue($falloCapturado, 'Debe lanzar RuntimeException para activar reintentos.');

        $registro = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame('FALLIDO', $registro->estado);

        // En el reintento, el mock tiene éxito y el job concluye
        $telegramMock2 = Mockery::mock(TelegramService::class);
        $telegramMock2->shouldReceive('enviarMensajeDirectoDetallado')
            ->once()
            ->with('CHAT_105', 'DOCENTE CON REINTENTO', Mockery::pattern('/Mensaje con fallo inicial/'))
            ->andReturn(['status' => 'Enviado a proveedor', 'success' => true]);

        $job->handle($telegramMock2);

        $registroActualizado = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertSame('ACEPTADO_PROVEEDOR', $registroActualizado->estado);
    }

    public function test_persistencia_recupera_tras_reinicio_de_worker_y_previene_duplicados_concurrencia(): void
    {
        $personal = Personal::create([
            'id' => 'DOC_106',
            'nombre_completo' => 'Docente Concurrente',
            'telegram_chat_id' => 'CHAT_106',
            'activo' => 1,
        ]);

        $telegramMock = Mockery::mock(TelegramService::class);
        $telegramMock->shouldReceive('enviarMensajeDirectoDetallado')
            ->once()
            ->with('CHAT_106', 'DOCENTE CONCURRENTE', Mockery::pattern('/Mensaje concurrente/'))
            ->andReturn(['status' => 'Enviado a proveedor', 'success' => true]);

        $idempotencyKey = 'concurrent_key_'.uniqid();

        // Worker A procesa
        $workerA = new NotificarAvisoPersonalJob($personal, 'Mensaje concurrente', $idempotencyKey);
        $workerA->handle($telegramMock);

        // Simulación de reinicio del worker (nueva instancia con la misma clave)
        $workerB = new NotificarAvisoPersonalJob($personal, 'Mensaje concurrente', $idempotencyKey);
        $workerB->handle($telegramMock);

        // Verificar que en base de datos solo existe 1 registro y su estado es ENTREGADO
        $registros = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->get();

        $this->assertCount(1, $registros);
        $this->assertSame('ACEPTADO_PROVEEDOR', $registros->first()->estado);
    }

    public function test_recuperacion_de_registro_abandonado_en_proceso_por_caida_de_worker(): void
    {
        $personal = Personal::create([
            'id' => 'DOC_107',
            'nombre_completo' => 'Docente Zombie Recovery',
            'telegram_chat_id' => 'CHAT_107',
            'activo' => 1,
        ]);

        $idempotencyKey = 'zombie_key_'.uniqid();

        // Simular un worker previo que murió abruptamente hace 15 minutos en estado EN_PROCESO
        DB::table('notificaciones_procesadas')->insert([
            'idempotency_key' => $idempotencyKey,
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => 'DOC_107',
            'canal' => 'Telegram',
            'estado' => 'EN_PROCESO',
            'intentos' => 1,
            'created_at' => Carbon::now()->subMinutes(15),
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);

        $telegramMock = Mockery::mock(TelegramService::class);
        $telegramMock->shouldReceive('enviarMensajeDirectoDetallado')
            ->once()
            ->with('CHAT_107', 'DOCENTE ZOMBIE RECOVERY', Mockery::pattern('/Mensaje tras recuperacion/'))
            ->andReturn(['status' => 'Enviado a proveedor', 'success' => true]);

        $worker = new NotificarAvisoPersonalJob($personal, 'Mensaje tras recuperacion', $idempotencyKey);
        $worker->handle($telegramMock);

        $registro = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame('ACEPTADO_PROVEEDOR', $registro->estado);
        $this->assertEquals(2, $registro->intentos);
    }

    public function test_job_failed_marca_estado_fallido_y_registra_trazabilidad(): void
    {
        $personal = Personal::create([
            'id' => 'DOC_108',
            'nombre_completo' => 'Docente Fallo Definitivo',
            'telegram_chat_id' => 'CHAT_108',
            'activo' => 1,
        ]);

        $idempotencyKey = 'failed_key_'.uniqid();

        DB::table('notificaciones_procesadas')->insert([
            'idempotency_key' => $idempotencyKey,
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => 'DOC_108',
            'canal' => 'Telegram',
            'estado' => 'EN_PROCESO',
            'intentos' => 3,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $job = new NotificarAvisoPersonalJob($personal, 'Mensaje que fallara', $idempotencyKey);
        $job->failed(new \RuntimeException('Connection refused by remote host.'));

        $registro = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame('FALLIDO', $registro->estado);
        $this->assertStringContainsString('Agotados los 3 intentos', $registro->ultimo_error);
        $this->assertStringNotContainsString('Connection refused', $registro->ultimo_error);
    }
}
