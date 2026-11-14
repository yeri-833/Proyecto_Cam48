<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Evento;
use App\Models\EventoConvocatoria;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class EventoController extends Controller
{
    /**
     * Muestra el calendario / agenda de eventos
     */
    public function index()
    {
        Gate::authorize('eventos.consultar');

        // Traemos los eventos desde hoy en adelante
        $eventos = Evento::whereDate('fecha_hora', '>=', Carbon::today())
            ->orderBy('fecha_hora', 'asc')
            ->get()
            ->map(function ($evento) {
                $fecha = Carbon::parse($evento->fecha_hora);
                $evento->fecha_formateada = $fecha->translatedFormat('d M Y');
                $evento->hora_formateada = $fecha->format('h:i A');
                $evento->es_hoy = $fecha->isToday();

                return $evento;
            });

        return Inertia::render('Agenda/Index', [
            'eventos' => $eventos,
        ]);
    }

    /**
     * Guarda o actualiza un evento
     */
    public function store(Request $request)
    {
        Gate::authorize('eventos.crear');

        $validated = $request->validate([
            'id' => 'nullable|exists:eventos,id',
            'nombre_evento' => 'required|string|max:255',
            'fecha_hora' => 'required|date',
            'ubicacion' => 'nullable|string|max:255',
        ]);

        if (isset($validated['id']) && $validated['id']) {
            $evento = Evento::findOrFail($validated['id']);
            $evento->update([
                'nombre_evento' => $validated['nombre_evento'],
                'fecha_hora' => $validated['fecha_hora'],
                'ubicacion' => $validated['ubicacion'] ?? '',
            ]);
            $msg = 'Evento actualizado correctamente.';
        } else {
            $evento = Evento::create([
                'nombre_evento' => $validated['nombre_evento'],
                'fecha_hora' => $validated['fecha_hora'],
                'ubicacion' => $validated['ubicacion'] ?? '',
            ]);

            // Alertas automáticas para tutores y canal institucional
            $fechaFormateada = Carbon::parse($validated['fecha_hora'])->translatedFormat('d \d\e F \d\e Y \a \l\a\s h:i A');
            $ubicacionFinal = $validated['ubicacion'] ?? 'Por confirmar';

            // 1. Envíos encolados por tutor (WhatsApp, Telegram e Email vía NotificarEventoTutorJob)
            $wsService = new WhatsAppService;
            $wsService->enviarAvisoEvento(
                $validated['nombre_evento'],
                $fechaFormateada,
                $ubicacionFinal,
                $evento->id
            );

            // 2. Envío al canal general institucional si está configurado
            $tgService = new TelegramService;
            $tgService->enviarAvisoEvento(
                $validated['nombre_evento'],
                $fechaFormateada,
                $ubicacionFinal
            );

            $msg = 'Evento registrado correctamente. Las notificaciones se procesarán según la configuración del sistema.';
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Elimina un evento
     */
    public function destroy($id)
    {
        Gate::authorize('eventos.eliminar');

        $evento = Evento::findOrFail($id);
        $evento->delete();

        return redirect()->back()->with('success', 'Evento cancelado correctamente.');
    }

    /**
     * Consulta el listado de convocatorias para un evento escolar.
     */
    public function convocatorias($id)
    {
        Gate::authorize('eventos.consultar');

        $evento = Evento::findOrFail($id);

        if (! Schema::hasTable('evento_convocatorias')) {
            return response()->json([
                'evento' => $evento,
                'convocatorias' => [],
            ]);
        }

        $convocatorias = DB::table('evento_convocatorias')
            ->leftJoin('tutores', 'evento_convocatorias.tutor_id', '=', 'tutores.id')
            ->leftJoin('alumnos', 'evento_convocatorias.alumno_matricula', '=', 'alumnos.matricula')
            ->where('evento_convocatorias.evento_id', $evento->id)
            ->select([
                'evento_convocatorias.id',
                'evento_convocatorias.evento_id',
                'evento_convocatorias.tutor_id',
                'evento_convocatorias.alumno_matricula',
                'evento_convocatorias.notificado',
                'evento_convocatorias.asistio',
                'evento_convocatorias.fecha_asistencia',
                'tutores.t1_nombre as tutor_nombre',
                'tutores.t1_telefono as tutor_telefono',
                'tutores.t1_email as tutor_email',
                'alumnos.nombre as alumno_nombre',
                'alumnos.apellido as alumno_apellido',
                'alumnos.grado as alumno_grado',
                'alumnos.grupo as alumno_grupo',
            ])
            ->orderBy('tutores.t1_nombre', 'asc')
            ->get();

        return response()->json([
            'evento' => $evento,
            'convocatorias' => $convocatorias,
        ]);
    }

    /**
     * Convoca tutores a un evento escolar (por tutor_ids, grado/grupo o matrículas de alumnos).
     */
    public function convocar(Request $request, $id)
    {
        Gate::authorize('eventos.crear');

        $evento = Evento::findOrFail($id);

        $validated = $request->validate([
            'tutores_ids' => 'nullable|array',
            'tutores_ids.*' => 'exists:tutores,id',
            'alumnos_matriculas' => 'nullable|array',
            'alumnos_matriculas.*' => 'exists:alumnos,matricula',
            'grado' => 'nullable|string|max:10',
            'grupo' => 'nullable|string|max:10',
        ]);

        if (! Schema::hasTable('evento_convocatorias')) {
            return redirect()->back()->with('warning', 'La tabla de convocatorias no está disponible en este entorno.');
        }

        $tutoresAConvocar = collect();

        // 1. Tutores directos por ID
        if (! empty($validated['tutores_ids'])) {
            $tutoresDirectos = Tutor::whereIn('id', $validated['tutores_ids'])->get();
            $tutoresAConvocar = $tutoresAConvocar->merge($tutoresDirectos);
        }

        // 2. Alumnos por grado/grupo
        if (! empty($validated['grado']) && ! empty($validated['grupo'])) {
            $alumnosGrupo = Alumno::where('activo', 1)
                ->where('grado', $validated['grado'])
                ->where('grupo', $validated['grupo'])
                ->pluck('matricula')
                ->all();

            if (! empty($alumnosGrupo)) {
                $validated['alumnos_matriculas'] = array_unique(array_merge(
                    $validated['alumnos_matriculas'] ?? [],
                    $alumnosGrupo
                ));
            }
        }

        // 3. Tutores vinculados a alumnos especificados
        if (! empty($validated['alumnos_matriculas'])) {
            $matriculas = $validated['alumnos_matriculas'];

            // Buscar mediante tabla alumno_tutor
            if (Schema::hasTable('alumno_tutor')) {
                $tutorIdsFromPivot = DB::table('alumno_tutor')
                    ->whereIn('alumno_matricula', $matriculas)
                    ->pluck('tutor_id')
                    ->all();

                if (! empty($tutorIdsFromPivot)) {
                    $tutoresPivot = Tutor::whereIn('id', $tutorIdsFromPivot)->get();
                    $tutoresAConvocar = $tutoresAConvocar->merge($tutoresPivot);
                }
            }

            // Buscar mediante matricula_alumno histórica en tutores
            $tutoresHistoricos = Tutor::whereIn('matricula_alumno', $matriculas)->get();
            $tutoresAConvocar = $tutoresAConvocar->merge($tutoresHistoricos);
        }

        $tutoresAConvocar = $tutoresAConvocar->unique('id');

        if ($tutoresAConvocar->isEmpty()) {
            return redirect()->back()->with('warning', 'No se encontraron tutores para los criterios especificados.');
        }

        $convocadosCount = 0;
        foreach ($tutoresAConvocar as $tutor) {
            $inserted = DB::table('evento_convocatorias')->insertOrIgnore([
                'evento_id' => $evento->id,
                'tutor_id' => $tutor->id,
                'alumno_matricula' => $tutor->matricula_alumno,
                'notificado' => false,
                'asistio' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted > 0) {
                $convocadosCount++;
            }
        }

        return redirect()->back()->with('success', "Se han convocado {$convocadosCount} tutores a la junta escolar '{$evento->nombre_evento}'.");
    }

    /**
     * Remueve la convocatoria de un tutor para un evento específico.
     */
    public function desconvocar(Request $request, $id, $tutorId)
    {
        Gate::authorize('eventos.eliminar');

        if (Schema::hasTable('evento_convocatorias')) {
            DB::table('evento_convocatorias')
                ->where('evento_id', $id)
                ->where('tutor_id', $tutorId)
                ->delete();
        }

        return redirect()->back()->with('success', 'Convocatoria removida correctamente.');
    }

    /**
     * Registra manualmente la asistencia de un padre/tutor al evento (sin código QR).
     */
    public function marcarAsistenciaManual(Request $request, $id, $tutorId)
    {
        Gate::authorize('eventos.crear');

        $evento = Evento::findOrFail($id);
        $tutor = Tutor::findOrFail($tutorId);

        // Verificar si ya tiene asistencia para este evento
        $yaAsistio = RegistroAsistencia::where('asistible_type', Tutor::class)
            ->where('asistible_id', $tutor->id)
            ->where('evento_id', $evento->id)
            ->exists();

        if ($yaAsistio) {
            return redirect()->back()->with('warning', 'El tutor ya tiene asistencia registrada para este evento.');
        }

        $hoy = Carbon::today()->toDateString();
        $hora = Carbon::now()->toTimeString();

        // Registrar asistencia polimórfica manual
        RegistroAsistencia::create([
            'asistible_type' => Tutor::class,
            'asistible_id' => $tutor->id,
            'id_persona' => (string) $tutor->id,
            'rol' => 'Tutor',
            'modo_registro' => 'MANUAL',
            'evento_id' => $evento->id,
            'fecha' => $hoy,
            'hora' => $hora,
        ]);

        // Actualizar o crear convocatoria en evento_convocatorias
        if (Schema::hasTable('evento_convocatorias')) {
            $updated = DB::table('evento_convocatorias')
                ->where('evento_id', $evento->id)
                ->where('tutor_id', $tutor->id)
                ->update([
                    'asistio' => true,
                    'fecha_asistencia' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

            if ($updated === 0) {
                DB::table('evento_convocatorias')->insertOrIgnore([
                    'evento_id' => $evento->id,
                    'tutor_id' => $tutor->id,
                    'alumno_matricula' => $tutor->matricula_alumno,
                    'notificado' => false,
                    'asistio' => true,
                    'fecha_asistencia' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        return redirect()->back()->with('success', "Asistencia de {$tutor->nombre_completo} confirmada manualmente para '{$evento->nombre_evento}'.");
    }
}
