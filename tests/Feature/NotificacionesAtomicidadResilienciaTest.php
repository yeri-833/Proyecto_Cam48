<?php

namespace Tests\Feature;

use App\Jobs\NotificarAvisoPersonalJob;
use App\Models\Personal;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class NotificacionesAtomicidadResilienciaTest extends TestCase
{
    private Personal $personal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personal = Personal::create([
            'id' => 'PERS_RESIL_01',
            'nombre_completo' => 'Profesor Carlos Mendoza',
            'email' => 'carlos.mendoza@escuela.test',
            'telefono' => '9219991122',
            'telegram_chat_id' => '123456789',
            'activo' => true,
        ]);
    }

    public function test_dos_workers_compitiendo_solo_uno_obtiene_la_reserva_atomica(): void
    {
        $idempotencyKey = hash('sha256', "aviso_personal:personal:{$this->personal->id}:asunto:Circular Urgente:mensaje:Reunion");

        // Worker 1 adquiere el lease con su token de propietario
        $ownerToken1 = Str::random(32);
        $adquirido1 = DB::table('notificaciones_procesadas')->insert([
            'idempotency_key' => $idempotencyKey,
            'canal' => 'TELEGRAM',
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'EN_PROCESO',
            'owner_token' => $ownerToken1,
            'lease_expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $this->assertTrue($adquirido1);

        // Worker 2 intenta adquirir el lease para la misma clave mientras el lease sigue vigente
        $ownerToken2 = Str::random(32);
        $adquirido2 = false;
        try {
            $adquirido2 = DB::table('notificaciones_procesadas')->insert([
                'idempotency_key' => $idempotencyKey,
                'canal' => 'TELEGRAM',
                'destinatario_tipo' => 'Personal',
                'destinatario_id' => (string) $this->personal->id,
                'estado' => 'EN_PROCESO',
                'owner_token' => $ownerToken2,
                'lease_expires_at' => Carbon::now()->addMinutes(5),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            // Falla por clave única primaria en idempotency_key
            $adquirido2 = false;
        }

        $this->assertFalse($adquirido2);

        // Worker 2 intenta robar el lease mediante update, pero el lease no ha expirado
        $filasActualizadas = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->where('estado', 'EN_PROCESO')
            ->where('lease_expires_at', '<', Carbon::now())
            ->update([
                'owner_token' => $ownerToken2,
                'lease_expires_at' => Carbon::now()->addMinutes(5),
            ]);

        $this->assertSame(0, $filasActualizadas, 'Worker 2 no debe poder robar un lease activo');
    }

    public function test_recuperacion_de_notificacion_tras_muerte_o_abandono_de_worker_por_lease_expirado(): void
    {
        $idempotencyKey = hash('sha256', "aviso_personal:personal:{$this->personal->id}:asunto:Circular:mensaje:Texto");

        // Worker caído dejó el registro en EN_PROCESO con lease en el pasado (-10 minutos)
        $zombieToken = Str::random(32);
        DB::table('notificaciones_procesadas')->insert([
            'idempotency_key' => $idempotencyKey,
            'canal' => 'TELEGRAM',
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'EN_PROCESO',
            'owner_token' => $zombieToken,
            'lease_expires_at' => Carbon::now()->subMinutes(10),
            'created_at' => Carbon::now()->subMinutes(15),
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);

        $tgMock = Mockery::mock(TelegramService::class);
        $tgMock->shouldReceive('enviarMensajeDirecto')->once()->andReturn(true);

        // Nuevo worker entra a procesar el job
        $job = new NotificarAvisoPersonalJob(
            $this->personal,
            'Texto',
            $idempotencyKey
        );

        $job->handle($tgMock);

        // El registro debe haber sido rescatado y completado como ACEPTADO_PROVEEDOR
        $registro = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame('ACEPTADO_PROVEEDOR', $registro->estado);
        $this->assertNotEquals($zombieToken, $registro->owner_token, 'El owner_token debió ser renovado por el nuevo worker');
    }

    public function test_worker_zombie_desfasado_no_puede_sobrescribir_registro_tomado_por_nuevo_worker(): void
    {
        $idempotencyKey = hash('sha256', "aviso_personal:personal:{$this->personal->id}:asunto:Circular Z:mensaje:Texto Z");

        $nuevoWorkerToken = Str::random(32);
        $zombieWorkerToken = Str::random(32);

        // El nuevo worker tomó legítimamente el lease
        DB::table('notificaciones_procesadas')->insert([
            'idempotency_key' => $idempotencyKey,
            'canal' => 'TELEGRAM',
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'EN_PROCESO',
            'owner_token' => $nuevoWorkerToken,
            'lease_expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // El worker zombie despierta y cree que aún tiene el lease, intentando actualizar con su zombie token
        $afectados = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->where('owner_token', $zombieWorkerToken)
            ->update([
                'estado' => 'ENTREGADO',
                'updated_at' => Carbon::now(),
            ]);

        $this->assertSame(0, $afectados, 'El worker zombie no debe modificar registros que ya no le pertenecen');

        // El registro legítimo sigue perteneciendo al nuevo worker
        $actual = DB::table('notificaciones_procesadas')->where('idempotency_key', $idempotencyKey)->first();
        $this->assertSame($nuevoWorkerToken, $actual->owner_token);
        $this->assertSame('EN_PROCESO', $actual->estado);
    }

    public function test_job_no_marca_entregado_si_el_proveedor_devuelve_falso(): void
    {
        $tgMock = Mockery::mock(TelegramService::class);
        $tgMock->shouldReceive('enviarMensajeDirecto')->once()->andReturn(false);

        $idempotencyKey = hash('sha256', "aviso_personal:personal:{$this->personal->id}:asunto:Falla:mensaje:NoLlega");

        $job = new NotificarAvisoPersonalJob(
            $this->personal,
            'NoLlega',
            $idempotencyKey
        );

        $falloCapturado = false;
        try {
            $job->handle($tgMock);
        } catch (\RuntimeException $e) {
            $falloCapturado = true;
        }

        $this->assertTrue($falloCapturado, 'El job debió lanzar RuntimeException para indicar fallo y reintento');

        $this->assertDatabaseHas('notificaciones_procesadas', [
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'FALLIDO',
        ]);

        $this->assertDatabaseMissing('notificaciones_procesadas', [
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'ACEPTADO_PROVEEDOR',
        ]);
        $this->assertDatabaseMissing('notificaciones_procesadas', [
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'ENTREGADO',
        ]);
    }

    public function test_personal_sin_canal_registrado_marca_sin_canal_disponible_y_no_entregado(): void
    {
        $personalSinCanal = Personal::create([
            'id' => 'PERS_NO_CH_01',
            'nombre_completo' => 'Docente Sin Telegram',
            'telegram_chat_id' => null,
            'activo' => true,
        ]);

        $tgMock = Mockery::mock(TelegramService::class);
        $tgMock->shouldNotReceive('enviarMensajeDirecto');

        $key = hash('sha256', 'aviso:sin_canal:'.uniqid());
        $job = new NotificarAvisoPersonalJob($personalSinCanal, 'Mensaje sin canal', $key);
        $job->handle($tgMock);

        $registro = DB::table('notificaciones_procesadas')->where('idempotency_key', $key)->first();
        $this->assertNotNull($registro);
        $this->assertSame('SIN_CANAL_DISPONIBLE', $registro->estado);
        $this->assertNull($registro->fecha_entrega);
        $detalle = json_decode((string) $registro->detalle_canales, true);
        $this->assertSame('NO_CONFIGURADO', $detalle['telegram']['estado'] ?? $detalle['telegram'] ?? null);
    }

    public function test_asistencia_parcial_cuando_whatsapp_falla_y_telegram_acepta_preserva_ambos_resultados(): void
    {
        $alumno = \App\Models\Alumno::create([
            'matricula' => 'ALU_PARC_01',
            'nombre' => 'Elena',
            'apellido' => 'Vega',
            'activo' => 1,
        ]);

        $tutor = \App\Models\Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'Mario Vega',
            't1_telefono' => '9211112233',
            't1_telegram_chat_id' => '99887711',
        ]);
        $alumno->setRelation('tutor', $tutor);

        $wsMock = Mockery::mock(\App\Services\WhatsAppService::class);
        $wsMock->shouldReceive('enviarAvisoAsistencia')->once()->andReturn(false);

        $tgMock = Mockery::mock(TelegramService::class);
        $tgMock->shouldReceive('enviarAvisoAsistencia')->once()->andReturn(true);

        $job = new \App\Jobs\NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Elena Vega', '2026-11-13');
        try {
            $job->handle($wsMock, $tgMock);
            $this->fail('Debió lanzar RuntimeException para forzar reintento del canal pendiente');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Entrega parcial', $e->getMessage());
        }

        $registro = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $job->resolverIdempotencyKey())
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame('PARCIAL', $registro->estado);
        $this->assertStringContainsString('WhatsApp', (string) $registro->ultimo_error);
        $detalle = json_decode((string) $registro->detalle_canales, true);
        $this->assertSame('FALLIDO', $detalle['whatsapp']['estado'] ?? $detalle['whatsapp'] ?? null);
        $this->assertSame('ACEPTADO_PROVEEDOR', $detalle['telegram']['estado'] ?? $detalle['telegram'] ?? null);
    }

    public function test_recuperacion_independiente_reintenta_solo_canal_fallido_sin_reenviar_el_ya_aceptado(): void
    {
        $alumno = \App\Models\Alumno::create([
            'matricula' => 'ALUM_RECUP_01',
            'nombre' => 'Mateo',
            'apellido' => 'Sosa',
            'activo' => true,
        ]);

        $tutor = \App\Models\Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'Lorena Sosa',
            't1_telefono' => '9217778899',
            't1_telegram_chat_id' => '11224455',
        ]);
        $alumno->setRelation('tutor', $tutor);

        // Intento 1: WhatsApp falla, Telegram es aceptado por el proveedor
        $wsMock1 = Mockery::mock(\App\Services\WhatsAppService::class);
        $wsMock1->shouldReceive('enviarAvisoAsistencia')->once()->andReturn(false);

        $tgMock1 = Mockery::mock(TelegramService::class);
        $tgMock1->shouldReceive('enviarAvisoAsistencia')->once()->andReturn(true);

        $job1 = new \App\Jobs\NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Mateo Sosa', '2026-11-14');
        try {
            $job1->handle($wsMock1, $tgMock1);
        } catch (\RuntimeException $e) {
            // Esperado para reintento de cola
        }

        $reg1 = DB::table('notificaciones_procesadas')->where('idempotency_key', $job1->resolverIdempotencyKey())->first();
        $this->assertSame('PARCIAL', $reg1->estado);

        // Intento 2 (Reintento de la cola):
        // REGLA FUNDAMENTAL: Telegram ya fue aceptado, por lo tanto NUNCA debe volver a llamarse
        $tgMock2 = Mockery::mock(TelegramService::class);
        $tgMock2->shouldNotReceive('enviarAvisoAsistencia');

        // WhatsApp falló previamente, por lo que SÍ debe reintentarse
        $wsMock2 = Mockery::mock(\App\Services\WhatsAppService::class);
        $wsMock2->shouldReceive('enviarAvisoAsistencia')->once()->andReturn(true);

        $job2 = new \App\Jobs\NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Mateo Sosa', '2026-11-14');
        $job2->handle($wsMock2, $tgMock2);

        $reg2 = DB::table('notificaciones_procesadas')->where('idempotency_key', $job2->resolverIdempotencyKey())->first();
        $this->assertSame('ACEPTADO_PROVEEDOR', $reg2->estado);
        $det2 = json_decode((string) $reg2->detalle_canales, true);
        $this->assertSame('ACEPTADO_PROVEEDOR', $det2['whatsapp']['estado'] ?? null);
        $this->assertSame('ACEPTADO_PROVEEDOR', $det2['telegram']['estado'] ?? null);
    }

    public function test_canal_con_timeout_se_marca_como_incierto_y_no_se_duplica_en_siguiente_intento(): void
    {
        $alumno = \App\Models\Alumno::create([
            'matricula' => 'ALUM_TIMEOUT_01',
            'nombre' => 'Lucía',
            'apellido' => 'Méndez',
            'activo' => true,
        ]);

        $tutor = \App\Models\Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'Roberto Méndez',
            't1_telefono' => '9213334455',
            't1_telegram_chat_id' => '66778899',
        ]);
        $alumno->setRelation('tutor', $tutor);

        // Intento 1: WhatsApp sufre timeout de red (resultado incierto)
        $wsMock1 = Mockery::mock(\App\Services\WhatsAppService::class);
        $wsMock1->shouldReceive('enviarAvisoAsistencia')
            ->once()
            ->andThrow(new \RuntimeException('cURL error 28: Operation timed out after 5000 milliseconds'));

        $tgMock1 = Mockery::mock(TelegramService::class);
        $tgMock1->shouldReceive('enviarAvisoAsistencia')->once()->andReturn(true);

        $job1 = new \App\Jobs\NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Lucía Méndez', '2026-11-14');
        $job1->handle($wsMock1, $tgMock1);

        $reg1 = DB::table('notificaciones_procesadas')->where('idempotency_key', $job1->resolverIdempotencyKey())->first();
        $det1 = json_decode((string) $reg1->detalle_canales, true);
        $this->assertSame('INCIERTO', $det1['whatsapp']['estado'] ?? null);
        $this->assertSame('ACEPTADO_PROVEEDOR', $det1['telegram']['estado'] ?? null);

        // Intento 2: Como WhatsApp quedó en INCIERTO (y Telegram ACEPTADO), ningún canal debe ser reenviado
        $wsMock2 = Mockery::mock(\App\Services\WhatsAppService::class);
        $wsMock2->shouldNotReceive('enviarAvisoAsistencia');

        $tgMock2 = Mockery::mock(TelegramService::class);
        $tgMock2->shouldNotReceive('enviarAvisoAsistencia');

        $job2 = new \App\Jobs\NotificarAsistenciaJob($alumno, 'ENTRADA', '08:00 AM', 'Lucía Méndez', '2026-11-14');
        $job2->handle($wsMock2, $tgMock2);

        $this->addToAssertionCount(1);
    }

    public function test_failed_no_sobreescribe_lease_activo_de_otro_worker_ni_estado_completado(): void
    {
        $key = hash('sha256', 'aviso:failed_guard:'.uniqid());
        $tokenActivo = Str::random(32);

        DB::table('notificaciones_procesadas')->insert([
            'idempotency_key' => $key,
            'canal' => 'Telegram',
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'EN_PROCESO',
            'owner_token' => $tokenActivo,
            'lease_expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Un job sin token de propiedad (o con token obsoleto) invoca failed()
        $jobAjeno = new NotificarAvisoPersonalJob($this->personal, 'Texto', $key);
        $jobAjeno->failed(new \RuntimeException('Timeout tardío'));

        $registro = DB::table('notificaciones_procesadas')->where('idempotency_key', $key)->first();
        $this->assertSame('EN_PROCESO', $registro->estado);
        $this->assertSame($tokenActivo, $registro->owner_token);
    }
}
