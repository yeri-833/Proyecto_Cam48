<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RegistroAsistencia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistorialController extends Controller
{
    /**
     * Mapa de asistible_type a etiqueta de rol legible.
     */
    private const TYPE_TO_ROL = [
        \App\Models\Alumno::class   => 'Alumno',
        \App\Models\Tutor::class    => 'Tutor',
        \App\Models\Personal::class => 'Personal',
    ];

    private const ROL_TO_TYPE = [
        'Alumno'   => \App\Models\Alumno::class,
        'Tutor'    => \App\Models\Tutor::class,
        'Personal' => \App\Models\Personal::class,
    ];

    /**
     * Base query usando el esquema polimórfico (asistible_type / asistible_id).
     */
    private function getBaseQuery(string $fecha, string $busqueda, string $rol)
    {
        // Unimos las tres tablas de personas con LEFT JOIN condicional
        $query = DB::table('registros_asistencia as r')
            ->leftJoin('alumnos as a', function ($join) {
                $join->on('r.asistible_id', '=', 'a.matricula')
                     ->where('r.asistible_type', '=', \App\Models\Alumno::class);
            })
            ->leftJoin('personal as p', function ($join) {
                $join->on('r.asistible_id', '=', 'p.id')
                     ->where('r.asistible_type', '=', \App\Models\Personal::class);
            })
            ->leftJoin('tutores as t', function ($join) {
                $join->on('r.asistible_id', '=', 't.t1_telefono')
                     ->where('r.asistible_type', '=', \App\Models\Tutor::class);
            })
            ->whereDate('r.fecha', $fecha)
            ->whereNull('t.deleted_at'); // Ignorar tutores en soft-delete

        // Filtro de rol (mapea label → FQCN)
        if ($rol && $rol !== 'Todos' && isset(self::ROL_TO_TYPE[$rol])) {
            $query->where('r.asistible_type', self::ROL_TO_TYPE[$rol]);
        }

        // Búsqueda por nombre o ID
        if (!empty($busqueda)) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('r.asistible_id',     'LIKE', "%{$busqueda}%")
                  ->orWhere('a.nombre',          'LIKE', "%{$busqueda}%")
                  ->orWhere('a.apellido',        'LIKE', "%{$busqueda}%")
                  ->orWhere('p.nombre_completo', 'LIKE', "%{$busqueda}%")
                  ->orWhere('t.t1_nombre',       'LIKE', "%{$busqueda}%")
                  ->orWhere('t.t1_apellido',     'LIKE', "%{$busqueda}%");
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
     * Muestra el historial de asistencias (Pase de Lista) de un día específico.
     */
    public function index(Request $request)
    {
        $fecha    = $request->query('fecha',    Carbon::today()->toDateString());
        $busqueda = $request->query('busqueda', '');
        $rol      = $request->query('rol',      'Todos');

        // ── Totales reales del día (sin búsqueda) ──────────────────
        $totalesRaw = RegistroAsistencia::whereDate('fecha', $fecha)
            ->select('asistible_type', DB::raw('COUNT(*) as total'))
            ->groupBy('asistible_type')
            ->get();

        $estadisticas = ['total' => 0, 'Alumno' => 0, 'Personal' => 0, 'Tutor' => 0];

        foreach ($totalesRaw as $row) {
            $label = $this->tipoARol($row->asistible_type);
            $estadisticas[$label] = (int) $row->total;
            $estadisticas['total'] += $row->total;
        }

        // ── Registros paginados ────────────────────────────────────
        $query = $this->getBaseQuery($fecha, $busqueda, $rol);

        $registros = $query->select(
                'r.id',
                'r.asistible_id as id_persona',
                'r.asistible_type',
                'r.fecha',
                'r.hora',
                DB::raw("COALESCE(
                    a.nombre || ' ' || COALESCE(a.apellido, ''),
                    p.nombre_completo,
                    t.t1_nombre || ' ' || COALESCE(t.t1_apellido, ''),
                    r.asistible_id
                ) as nombre_completo")
            )
            ->orderBy('r.hora', 'desc')
            ->paginate(50)
            ->withQueryString()
            ->through(function ($registro) {
                $registro->hora_formateada = Carbon::parse($registro->hora)->format('h:i A');
                $registro->nombre_completo = trim($registro->nombre_completo);
                // Convertir FQCN → label legible para el frontend
                $registro->rol = $this->tipoARol($registro->asistible_type);
                return $registro;
            });

        return Inertia::render('Asistencia/Historial', [
            'registros'      => $registros,
            'fechaFiltro'    => $fecha,
            'busquedaActual' => $busqueda,
            'rolActual'      => $rol,
            'estadisticas'   => $estadisticas,
        ]);
    }

    /**
     * Exporta el reporte de asistencias a CSV (Stream).
     */
    public function exportar(Request $request)
    {
        $fecha    = $request->query('fecha',    Carbon::today()->toDateString());
        $busqueda = $request->query('busqueda', '');
        $rol      = $request->query('rol',      'Todos');

        $query          = $this->getBaseQuery($fecha, $busqueda, $rol);
        $nombreArchivo  = "Reporte_Asistencias_{$fecha}.csv";

        $cabeceras = [
            'Content-type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$nombreArchivo}",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // BOM UTF-8 para compatibilidad con Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['Hora', 'Identificacion', 'Nombre Completo', 'Rol']);

            $query->select(
                'r.hora',
                'r.asistible_id',
                'r.asistible_type',
                DB::raw("COALESCE(
                    a.nombre || ' ' || COALESCE(a.apellido, ''),
                    p.nombre_completo,
                    t.t1_nombre || ' ' || COALESCE(t.t1_apellido, ''),
                    r.asistible_id
                ) as nombre_completo")
            )
            ->orderBy('r.hora', 'desc')
            ->chunk(500, function ($registros) use ($file) {
                foreach ($registros as $row) {
                    fputcsv($file, [
                        Carbon::parse($row->hora)->format('h:i A'),
                        $row->asistible_id,
                        trim($row->nombre_completo),
                        self::TYPE_TO_ROL[$row->asistible_type] ?? 'Desconocido',
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
        \Illuminate\Support\Facades\Gate::authorize('eliminar-registros');

        RegistroAsistencia::where('id', $id)->delete();

        return redirect()->back()->with('success', 'Registro eliminado correctamente.');
    }
}
