<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistorialController extends Controller
{
    /**
     * Mapa de asistible_type a etiqueta de rol legible.
     */
    private const TYPE_TO_ROL = [
        Alumno::class => 'Alumno',
        Tutor::class => 'Tutor',
        Personal::class => 'Personal',
    ];

    private const ROL_TO_TYPE = [
        'Alumno' => Alumno::class,
        'Tutor' => Tutor::class,
        'Personal' => Personal::class,
    ];

    /**
     * Base query usando el esquema polimórfico (asistible_type / asistible_id)
     * e integrando la relación de tutores con sus alumnos asociados.
     */
    private function getBaseQuery(string $fecha, string $busqueda, string $rol)
    {
        // Unimos las tres tablas de personas según asistible_type y la relación tutor-alumno
        $query = DB::table('registros_asistencia as r')
            ->leftJoin('alumnos as a', function ($join) {
                $join->on('r.asistible_id', '=', 'a.matricula')
                    ->where('r.asistible_type', '=', Alumno::class);
            })
            ->leftJoin('personal as p', function ($join) {
                $join->on('r.asistible_id', '=', DB::raw('CAST(p.id AS CHAR)'))
                    ->where('r.asistible_type', '=', Personal::class);
            })
            ->leftJoin('tutores as t', function ($join) {
                $join->on('r.asistible_id', '=', DB::raw('CAST(t.id AS CHAR)'))
                    ->where('r.asistible_type', '=', Tutor::class);
            })
            ->leftJoin('alumnos as a_tutor', 't.matricula_alumno', '=', 'a_tutor.matricula')
            ->whereDate('r.fecha', $fecha);

        // Filtro de rol
        if ($rol && $rol !== 'Todos' && isset(self::ROL_TO_TYPE[$rol])) {
            $query->where('r.asistible_type', self::ROL_TO_TYPE[$rol]);
        }

        // Búsqueda por nombre, ID o alumno relacionado
        if (! empty($busqueda)) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('r.asistible_id', 'LIKE', "%{$busqueda}%")
                    ->orWhere('a.nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('a.apellido', 'LIKE', "%{$busqueda}%")
                    ->orWhere('p.nombre_completo', 'LIKE', "%{$busqueda}%")
                    ->orWhere('t.t1_nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('a_tutor.nombre', 'LIKE', "%{$busqueda}%")
                    ->orWhere('a_tutor.apellido', 'LIKE', "%{$busqueda}%")
                    ->orWhere('t.matricula_alumno', 'LIKE', "%{$busqueda}%");
            });
        }

        return $query;
    }

    /**
     * Convierte asistible_type FQCN → etiqueta legible ('Alumno', 'Tutor', 'Personal').
     */
    private function tipoARol(string $type): string
    {
        return self::TYPE_TO_ROL[$type] ?? 'Desconocido';
    }

    /**
     * Retorna la expresión SQL para el nombre completo de la persona que asiste.
     */
    private function getNombreCompletoSql(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $alumnoName = "NULLIF(TRIM(COALESCE(a.nombre, '') || ' ' || COALESCE(a.apellido, '')), '')";
        } else {
            $alumnoName = "NULLIF(TRIM(CONCAT(COALESCE(a.nombre, ''), ' ', COALESCE(a.apellido, ''))), '')";
        }

        return "COALESCE({$alumnoName}, p.nombre_completo, t.t1_nombre, r.asistible_id) as nombre_completo";
    }

    /**
     * Retorna la expresión SQL para el nombre del alumno representado por el tutor.
     */
    private function getAlumnoRelacionadoSql(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "NULLIF(TRIM(COALESCE(a_tutor.nombre, '') || ' ' || COALESCE(a_tutor.apellido, '')), '') as alumno_relacionado";
        }

        return "NULLIF(TRIM(CONCAT(COALESCE(a_tutor.nombre, ''), ' ', COALESCE(a_tutor.apellido, ''))), '') as alumno_relacionado";
    }

    /**
     * Muestra el historial de asistencias (Pase de Lista) de un día específico.
     */
    public function index(Request $request)
    {
        Gate::authorize('historial.consultar');

        $fecha = $request->query('fecha', Carbon::today()->toDateString());
        $busqueda = $request->query('busqueda', '');
        $rol = $request->query('rol', 'Todos');

        // ── Totales reales del día ──────────────────
        $totalesRaw = DB::table('registros_asistencia')
            ->whereDate('fecha', $fecha)
            ->select('asistible_type', DB::raw('COUNT(*) as total'))
            ->groupBy('asistible_type')
            ->get();

        $estadisticas = ['total' => 0, 'Alumno' => 0, 'Personal' => 0, 'Tutor' => 0];

        foreach ($totalesRaw as $row) {
            $label = $this->tipoARol($row->asistible_type ?? '');
            $count = (int) $row->total;
            if (isset($estadisticas[$label])) {
                $estadisticas[$label] += $count;
            }
            $estadisticas['total'] += $count;
        }

        // ── Registros paginados con alumno relacionado ──────────────
        $query = $this->getBaseQuery($fecha, $busqueda, $rol);

        $registros = $query->select(
            'r.id',
            'r.asistible_id as id_persona',
            'r.asistible_type',
            'r.fecha',
            'r.hora',
            't.matricula_alumno',
            DB::raw($this->getNombreCompletoSql()),
            DB::raw($this->getAlumnoRelacionadoSql())
        )
            ->orderBy('r.hora', 'desc')
            ->paginate(50)
            ->withQueryString()
            ->through(function ($registro) {
                $registro->hora_formateada = Carbon::parse($registro->hora)->format('h:i A');
                $registro->nombre_completo = trim($registro->nombre_completo);
                $registro->rol = $this->tipoARol($registro->asistible_type ?? '');
                $registro->alumno_relacionado = $registro->rol === 'Tutor'
                    ? ($registro->alumno_relacionado ?: ($registro->matricula_alumno ? 'Matrícula: '.$registro->matricula_alumno : 'Sin asignar'))
                    : null;

                return $registro;
            });

        return Inertia::render('Asistencia/Historial', [
            'registros' => $registros,
            'fechaFiltro' => $fecha,
            'busquedaActual' => $busqueda,
            'rolActual' => $rol,
            'estadisticas' => $estadisticas,
        ]);
    }

    /**
     * Exporta el reporte de asistencias a CSV (Stream).
     */
    public function exportar(Request $request)
    {
        Gate::authorize('historial.exportar');

        $fecha = $request->query('fecha', Carbon::today()->toDateString());
        $busqueda = $request->query('busqueda', '');
        $rol = $request->query('rol', 'Todos');

        $query = $this->getBaseQuery($fecha, $busqueda, $rol);
        $nombreArchivo = "Reporte_Asistencias_{$fecha}.csv";

        $cabeceras = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$nombreArchivo}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // BOM UTF-8 para compatibilidad con Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Hora', 'Identificacion', 'Nombre Completo', 'Rol', 'Alumno Relacionado']);

            $query->select(
                'r.hora',
                'r.asistible_id',
                'r.asistible_type',
                't.matricula_alumno',
                DB::raw($this->getNombreCompletoSql()),
                DB::raw($this->getAlumnoRelacionadoSql())
            )
                ->orderBy('r.hora', 'desc')
                ->chunk(500, function ($registros) use ($file) {
                    foreach ($registros as $row) {
                        $rol = self::TYPE_TO_ROL[$row->asistible_type] ?? 'Desconocido';
                        $alumnoRel = $rol === 'Tutor'
                            ? ($row->alumno_relacionado ?: ($row->matricula_alumno ? 'Matrícula: '.$row->matricula_alumno : 'Sin asignar'))
                            : 'N/A';

                        fputcsv($file, [
                            Carbon::parse($row->hora)->format('h:i A'),
                            $row->asistible_id,
                            trim($row->nombre_completo),
                            $rol,
                            $alumnoRel,
                        ]);
                    }
                });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $cabeceras);
    }

    /**
     * Elimina un registro de asistencia por error.
     */
    public function destroy($id)
    {
        Gate::authorize('historial.eliminar');

        RegistroAsistencia::where('id', $id)->delete();

        return redirect()->back()->with('success', 'Registro eliminado correctamente.');
    }
}
