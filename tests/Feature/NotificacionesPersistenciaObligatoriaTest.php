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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotificacionesPersistenciaObligatoriaTest extends TestCase
{
    private Personal $personal;

    private Alumno $alumno;

    private Tutor $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personal = Personal::create([
            'id' => 'PERS_PERSIST_01',
            'nombre_completo' => 'Profesor Prueba Persistencia',
            'email' => 'prueba.persist@escuela.test',
            'telefono' => '9211112233',
            'telegram_chat_id' => '11223344',
            'activo' => true,
        ]);

        $this->tutor = Tutor::create([
            'matricula_alumno' => 'ALUM_PERSIST_01',
            't1_nombre' => 'Tutor Prueba',
            't1_telefono' => '9214445566',
            't1_email' => 'tutor.persist@escuela.test',
            't1_telegram_chat_id' => '55667788',
        ]);

        $this->alumno = Alumno::create([
            'matricula' => 'ALUM_PERSIST_01',
            'nombre' => 'Estudiante',
            'apellido' => 'Persistencia',
            'activo' => true,
        ]);
    }

    public function test_sin_tabla_notificaciones_procesadas_aviso_personal_aborta_sin_llamar_proveedor(): void
    {
        Schema::dropIfExists('notificaciones_procesadas');

        $telegramMock = Mockery::mock(TelegramService::class);
        $telegramMock->shouldNotReceive('enviarMensajeDirectoDetallado');

        $job = new NotificarAvisoPersonalJob($this->personal, 'Mensaje sin tabla', 'idemp_key_sin_tabla_pers');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("PERSISTENCIA OBLIGATORIA: La tabla 'notificaciones_procesadas' no existe");

        $job->handle($telegramMock);
    }

    public function test_sin_tabla_notificaciones_procesadas_asistencia_aborta_sin_llamar_proveedores(): void
    {
        Schema::dropIfExists('notificaciones_procesadas');

        $wsMock = Mockery::mock(WhatsAppService::class);
        $wsMock->shouldNotReceive('enviarAvisoAsistenciaDetallado');

        $tgMock = Mockery::mock(TelegramService::class);
        $tgMock->shouldNotReceive('enviarAvisoAsistenciaDetallado');

        $job = new NotificarAsistenciaJob($this->alumno, 'ENTRADA', '08:00 AM', 'Estudiante Persistencia');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("PERSISTENCIA OBLIGATORIA: La tabla 'notificaciones_procesadas' no existe");

        $job->handle($wsMock, $tgMock);
    }

    public function test_sin_tabla_notificaciones_procesadas_evento_aborta_sin_llamar_proveedores(): void
    {
        Schema::dropIfExists('notificaciones_procesadas');

        $wsMock = Mockery::mock(WhatsAppService::class);
        $wsMock->shouldNotReceive('enviarMensajeDirectoDetallado');

        $tgMock = Mockery::mock(TelegramService::class);
        $tgMock->shouldNotReceive('enviarAvisoEventoDirectoDetallado');

        Mail::fake();

        $job = new NotificarEventoTutorJob(
            $this->tutor,
            'Aviso de Evento',
            'Junta Escolar',
            '2026-11-20',
            'Auditorio',
            99
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("PERSISTENCIA OBLIGATORIA: La tabla 'notificaciones_procesadas' no existe");

        $job->handle($wsMock, $tgMock);
    }

    public function test_cuando_notificacion_ya_esta_completada_no_se_llaman_proveedores(): void
    {
        $idempKey = 'idemp_ya_completada_01';

        DB::table('notificaciones_procesadas')->insert([
            'idempotency_key' => $idempKey,
            'canal' => 'MULTICANAL',
            'destinatario_tipo' => 'Personal',
            'destinatario_id' => (string) $this->personal->id,
            'estado' => 'ACEPTADO_PROVEEDOR',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $telegramMock = Mockery::mock(TelegramService::class);
        $telegramMock->shouldNotReceive('enviarMensajeDirectoDetallado');

        $job = new NotificarAvisoPersonalJob($this->personal, 'Mensaje repetido', $idempKey);
        $job->handle($telegramMock);

        $this->addToAssertionCount(1);
    }
}
