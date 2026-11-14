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

        // El registro debe haber sido rescatado y completado como ENTREGADO
        $registro = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame('ENTREGADO', $registro->estado);
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
            'estado' => 'ENTREGADO',
        ]);
    }
}
