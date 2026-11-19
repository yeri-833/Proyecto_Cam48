<?php

namespace Tests\Feature;

use App\Jobs\NotificarEventoTutorJob;
use App\Models\Alumno;
use App\Models\Evento;
use App\Models\Tutor;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ConvocatoriasNotificacionesEventosTest extends TestCase
{
    private Tutor $tutor;

    private Evento $evento1;

    private Evento $evento2;

    private Evento $evento3;

    protected function setUp(): void
    {
        parent::setUp();

        $alumno = Alumno::create([
            'matricula' => 'CONV_ALU_01',
            'nombre' => 'Carlos',
            'apellido' => 'Ruiz',
            'activo' => 1,
        ]);

        $this->tutor = Tutor::create([
            'matricula_alumno' => $alumno->matricula,
            't1_nombre' => 'Elena Ruiz',
            't1_telefono' => '9211234567',
            't1_email' => 'elena.ruiz@test.com',
        ]);

        $this->evento1 = Evento::create([
            'nombre_evento' => 'Junta Matutina Primer Grado',
            'fecha_hora' => Carbon::today()->setTime(8, 0),
            'descripcion' => 'Auditorio A',
        ]);

        $this->evento2 = Evento::create([
            'nombre_evento' => 'Junta Vespertina Comite',
            'fecha_hora' => Carbon::today()->setTime(17, 0),
            'descripcion' => 'Auditorio B',
        ]);

        $this->evento3 = Evento::create([
            'nombre_evento' => 'Asamblea Extraordinaria',
            'fecha_hora' => Carbon::tomorrow()->setTime(10, 0),
            'descripcion' => 'Patio Central',
        ]);
    }

    public function test_tutor_convocado_a_tres_juntas_notificar_una_solo_actualiza_dicho_evento(): void
    {
        // Convocamos al tutor a las 3 juntas
        DB::table('evento_convocatorias')->insert([
            ['evento_id' => $this->evento1->id, 'tutor_id' => $this->tutor->id, 'notificado' => false, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $this->evento2->id, 'tutor_id' => $this->tutor->id, 'notificado' => false, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $this->evento3->id, 'tutor_id' => $this->tutor->id, 'notificado' => false, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $wsMock = Mockery::mock(WhatsAppService::class);
        $wsMock->shouldReceive('enviarMensajeDirecto')->once()->andReturn(true);

        $tgMock = Mockery::mock(TelegramService::class);

        // Despachamos la notificación exclusivamente para evento 1
        $job = new NotificarEventoTutorJob(
            $this->tutor,
            'Recordatorio Junta',
            $this->evento1->nombre_evento,
            $this->evento1->fecha_hora->format('d/m/Y H:i'),
            'Auditorio A',
            $this->evento1->id
        );

        $job->handle($wsMock, $tgMock);

        // Verificamos que evento 1 está notificado
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $this->evento1->id,
            'tutor_id' => $this->tutor->id,
            'notificado' => true,
        ]);

        // Verificamos que eventos 2 y 3 permanecen sin notificar
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $this->evento2->id,
            'tutor_id' => $this->tutor->id,
            'notificado' => false,
        ]);

        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $this->evento3->id,
            'tutor_id' => $this->tutor->id,
            'notificado' => false,
        ]);
    }

    public function test_fallo_de_proveedor_no_marca_convocatoria_como_notificada_ni_notificacion_como_entregada(): void
    {
        $tutorSinEmail = Tutor::create([
            'matricula_alumno' => 'CONV_ALU_01',
            't1_nombre' => 'Elena Ruiz Sin Mail',
            't1_telefono' => '9211234567',
            't1_email' => null,
        ]);

        DB::table('evento_convocatorias')->insert([
            'evento_id' => $this->evento1->id,
            'tutor_id' => $tutorSinEmail->id,
            'notificado' => false,
            'asistio' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wsMock = Mockery::mock(WhatsAppService::class);
        // Simulamos fallo del proveedor (ej. API WhatsApp offline)
        $wsMock->shouldReceive('enviarMensajeDirecto')->once()->andReturn(false);

        $tgMock = Mockery::mock(TelegramService::class);

        $job = new NotificarEventoTutorJob(
            $tutorSinEmail,
            'Recordatorio Junta',
            $this->evento1->nombre_evento,
            $this->evento1->fecha_hora->format('d/m/Y H:i'),
            'Auditorio A',
            $this->evento1->id
        );

        $falloCapturado = false;
        try {
            $job->handle($wsMock, $tgMock);
        } catch (\RuntimeException $e) {
            $falloCapturado = true;
        }

        $this->assertTrue($falloCapturado, 'El job debió lanzar excepción al fallar el canal');

        // La convocatoria NO debe estar notificada
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $this->evento1->id,
            'tutor_id' => $tutorSinEmail->id,
            'notificado' => false,
        ]);

        // La notificación en notificaciones_procesadas debe quedar en FALLIDO
        $this->assertDatabaseHas('notificaciones_procesadas', [
            'destinatario_tipo' => 'Tutor',
            'destinatario_id' => (string) $tutorSinEmail->id,
            'estado' => 'FALLIDO',
        ]);
    }

    public function test_dos_juntas_mismo_dia_tienen_idempotency_keys_independientes(): void
    {
        $wsMock = Mockery::mock(WhatsAppService::class);
        $wsMock->shouldReceive('enviarMensajeDirecto')->twice()->andReturn(true);

        $tgMock = Mockery::mock(TelegramService::class);

        // Notificación para Junta Matutina
        $job1 = new NotificarEventoTutorJob(
            $this->tutor,
            'Aviso 1',
            $this->evento1->nombre_evento,
            $this->evento1->fecha_hora->format('d/m/Y H:i'),
            'Auditorio A',
            $this->evento1->id
        );
        $job1->handle($wsMock, $tgMock);

        // Notificación para Junta Vespertina
        $job2 = new NotificarEventoTutorJob(
            $this->tutor,
            'Aviso 2',
            $this->evento2->nombre_evento,
            $this->evento2->fecha_hora->format('d/m/Y H:i'),
            'Auditorio B',
            $this->evento2->id
        );
        $job2->handle($wsMock, $tgMock);

        // Ambos registros deben coexistir de forma exitosa e independiente
        $registros = DB::table('notificaciones_procesadas')
            ->where('destinatario_tipo', 'Tutor')
            ->where('destinatario_id', (string) $this->tutor->id)
            ->where('estado', 'ACEPTADO_PROVEEDOR')
            ->get();

        $this->assertCount(2, $registros);
        $this->assertNotEquals($registros[0]->idempotency_key, $registros[1]->idempotency_key);
    }

    public function test_job_sin_evento_id_se_detiene_de_forma_segura_sin_tocar_convocatorias(): void
    {
        // Existen dos eventos con el MISMO nombre para demostrar que nunca se usa nombre_evento
        $eventoMismoNombre1 = Evento::create([
            'nombre_evento' => 'Junta Mensual Repetida',
            'fecha_hora' => now()->addDays(5),
            'ubicacion' => 'Salon 1',
        ]);
        $eventoMismoNombre2 = Evento::create([
            'nombre_evento' => 'Junta Mensual Repetida',
            'fecha_hora' => now()->addDays(10),
            'ubicacion' => 'Salon 2',
        ]);

        DB::table('evento_convocatorias')->insert([
            ['evento_id' => $eventoMismoNombre1->id, 'tutor_id' => $this->tutor->id, 'notificado' => false, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $eventoMismoNombre2->id, 'tutor_id' => $this->tutor->id, 'notificado' => false, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $wsMock = Mockery::mock(WhatsAppService::class);
        $wsMock->shouldNotReceive('enviarMensajeDirecto');

        $tgMock = Mockery::mock(TelegramService::class);
        $tgMock->shouldNotReceive('enviarAvisoEventoDirecto');

        // Despacho sin evento_id (null)
        $job = new NotificarEventoTutorJob(
            $this->tutor,
            'Aviso ambiguo',
            'Junta Mensual Repetida',
            '15/11/2026 10:00',
            'Salon 1',
            null
        );
        $job->handle($wsMock, $tgMock);

        // Ninguna convocatoria debe haber sido modificada
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $eventoMismoNombre1->id,
            'tutor_id' => $this->tutor->id,
            'notificado' => false,
        ]);
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $eventoMismoNombre2->id,
            'tutor_id' => $this->tutor->id,
            'notificado' => false,
        ]);
    }

    public function test_dos_eventos_mismo_nombre_y_tutor_compartido_no_interfieren_entre_si(): void
    {
        $eventoA = Evento::create([
            'nombre_evento' => 'Junta De Padres',
            'fecha_hora' => now()->addDays(6),
            'ubicacion' => 'Patio A',
        ]);
        $eventoB = Evento::create([
            'nombre_evento' => 'Junta De Padres',
            'fecha_hora' => now()->addDays(7),
            'ubicacion' => 'Patio B',
        ]);

        DB::table('evento_convocatorias')->insert([
            ['evento_id' => $eventoA->id, 'tutor_id' => $this->tutor->id, 'notificado' => false, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
            ['evento_id' => $eventoB->id, 'tutor_id' => $this->tutor->id, 'notificado' => false, 'asistio' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $wsMock = Mockery::mock(WhatsAppService::class);
        $wsMock->shouldReceive('enviarMensajeDirecto')->once()->andReturn(true);
        $tgMock = Mockery::mock(TelegramService::class);

        $job = new NotificarEventoTutorJob(
            $this->tutor,
            'Aviso Evento A',
            'Junta De Padres',
            '16/11/2026 10:00',
            'Patio A',
            $eventoA->id
        );
        $job->handle($wsMock, $tgMock);

        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $eventoA->id,
            'tutor_id' => $this->tutor->id,
            'notificado' => true,
        ]);
        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $eventoB->id,
            'tutor_id' => $this->tutor->id,
            'notificado' => false,
        ]);
    }

    public function test_desconvocatoria_y_registro_manual_independiente(): void
    {
        $admin = User::factory()->create(['role' => 'Director']);

        // Registrar convocatoria mediante la ruta autorizada
        $this->actingAs($admin)->post(route('eventos.convocar', $this->evento1->id), [
            'tutores_ids' => [$this->tutor->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $this->evento1->id,
            'tutor_id' => $this->tutor->id,
        ]);

        $this->post(route('eventos.asistenciaManual', [
            'id' => $this->evento2->id,
            'tutorId' => $this->tutor->id,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $convocatoriasAntes = DB::table('evento_convocatorias')->orderBy('id')->get()
            ->map(static fn (object $row): array => (array) $row)->all();
        $asistenciasAntes = DB::table('registros_asistencia')->orderBy('id')->get()
            ->map(static fn (object $row): array => (array) $row)->all();
        $this->assertCount(2, $convocatoriasAntes);
        $this->assertCount(1, $asistenciasAntes);

        $this->actingAs($admin)->delete(route('eventos.desconvocar', [
            'id' => $this->evento1->id,
            'tutorId' => $this->tutor->id,
        ]))->assertStatus(409);

        $this->assertDatabaseHas('evento_convocatorias', [
            'evento_id' => $this->evento1->id,
            'tutor_id' => $this->tutor->id,
            'asistio' => false,
        ]);
        $this->assertSame($convocatoriasAntes, DB::table('evento_convocatorias')->orderBy('id')->get()
            ->map(static fn (object $row): array => (array) $row)->all());
        $this->assertSame($asistenciasAntes, DB::table('registros_asistencia')->orderBy('id')->get()
            ->map(static fn (object $row): array => (array) $row)->all());
        $this->assertDatabaseHas('registros_asistencia', [
            'evento_id' => $this->evento2->id,
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $this->tutor->id,
            'modo_registro' => 'MANUAL',
        ]);
        $this->assertDatabaseMissing('registros_asistencia', [
            'evento_id' => $this->evento1->id,
            'asistible_type' => Tutor::class,
            'asistible_id' => (string) $this->tutor->id,
        ]);
    }
}
