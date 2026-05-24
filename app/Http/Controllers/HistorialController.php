<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistorialController extends Controller
{
    /**
     * Base query for fetching attendance records.
     */
    private function getBaseQuery($fecha, $busqueda, $rol)
    {
        $query = DB::table('registros_asistencia as r')
            ->leftJoin('alumnos as a', function($join) {
                $join->on('r.id_persona', '=', 'a.matricula')
                     ->where('r.rol', '=', 'Alumno');
            })
            ->leftJoin('personal as p', function($join) {
                $join->on('r.id_persona', '=', 'p.id')
                     ->where('r.rol', '!=', 'Alumno')
                     ->where('r.rol', '!=', 'Tutor');
            })
            ->leftJoin('tutores as t', function($join) {
                $join->on('r.id_persona', '=', 't.t1_telefono')
                     ->where('r.rol', '=', 'Tutor');
            })
            ->whereDate('r.fecha', $fecha);

        if ($rol && $rol !== 'Todos') {
            $query->where('r.rol', $rol);
        }

        if (!empty($busqueda)) {
            $query->where(function($q) use ($busqueda) {
                $q->where('r.id_persona', 'LIKE', "%{$busqueda}%")
                  ->orWhere('a.nombre', 'LIKE', "%{$busqueda}%")
                  ->orWhere('a.apellido', 'LIKE', "%{$busqueda}%")
                  ->orWhere('p.nombre_completo', 'LIKE', "%{$busqueda}%")
                  ->orWhere('t.t1_nombre', 'LIKE', "%{$busqueda}%");
            });
        }

        return $query;
    }

    /**
     * Muestra el historial de asistencias (Pase de Lista) de un día en específico.
     */
    public function index(Request $request)
    {
        $fecha = $request->query('fecha', Carbon::today()->toDateString());
        $busqueda = $request->query('busqueda', '');
        $rol = $request->query('rol', 'Todos');

        // 1. Obtener Estadísticas del día (Sin aplicar búsqueda, para saber totales reales del día)
        $totalesRaw = DB::table('registros_asistencia')
            ->whereDate('fecha', $fecha)
            ->select('rol', DB::raw('count(*) as total'))
            ->groupBy('rol')
            ->get();

        $estadisticas = [
            'total' => 0,
            'Alumno' => 0,
            'Personal' => 0,
            'Tutor' => 0
        ];
        
        foreach($totalesRaw as $row) {
            $estadisticas[$row->rol] = $row->total;
            $estadisticas['total'] += $row->total;
        }

        // 2. Obtener los registros paginados
        $query = $this->getBaseQuery($fecha, $busqueda, $rol);
        
        $registros = $query->select(
                'r.id',
                'r.id_persona',
                'r.rol',
                'r.fecha',
                'r.hora',
                DB::raw("COALESCE(a.nombre, p.nombre_completo, t.t1_nombre, r.id_persona) as nombre_persona"),
                DB::raw("COALESCE(a.apellido, '') as apellido_persona")
            )
            ->orderBy('r.hora', 'desc')
            ->paginate(50)
            ->withQueryString() // Mantiene los parámetros en la URL de paginación
            ->through(function ($registro) {
                $registro->hora_formateada = Carbon::parse($registro->hora)->format('h:i A');
                $registro->nombre_completo = trim($registro->nombre_persona . ' ' . $registro->apellido_persona);
                return $registro;
            });

        return Inertia::render('Asistencia/Historial', [
            'registros' => $registros,
            'fechaFiltro' => $fecha,
            'busquedaActual' => $busqueda,
            'rolActual' => $rol,
            'estadisticas' => $estadisticas
        ]);
    }

    /**
     * Exporta el reporte de asistencias actual a CSV (Stream)
     */
    public function exportar(Request $request)
    {
        $fecha = $request->query('fecha', Carbon::today()->toDateString());
        $busqueda = $request->query('busqueda', '');
        $rol = $request->query('rol', 'Todos');

        $query = $this->getBaseQuery($fecha, $busqueda, $rol);
        
        $registros = $query->select(
                'r.hora',
                'r.id_persona',
                DB::raw("COALESCE(a.nombre, p.nombre_completo, t.t1_nombre, r.id_persona) as nombre_persona"),
                DB::raw("COALESCE(a.apellido, '') as apellido_persona"),
                'r.rol'
            )
            ->orderBy('r.hora', 'desc')
            ->get();

        $nombreArchivo = "Reporte_Asistencias_" . $fecha . ".csv";

        $cabeceras = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$nombreArchivo",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($registros) {
            $file = fopen('php://output', 'w');
            
            // BOM for UTF-8 compatibility in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Encabezados
            fputcsv($file, ['Hora', 'Identificacion', 'Nombre Completo', 'Rol']);

            // Filas
            foreach ($registros as $row) {
                $nombreCompleto = trim($row->nombre_persona . ' ' . $row->apellido_persona);
                fputcsv($file, [
                    Carbon::parse($row->hora)->format('h:i A'),
                    $row->id_persona,
                    $nombreCompleto,
                    $row->rol
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $cabeceras);
    }

    /**
     * Elimina un registro de asistencia por error
     */
    public function destroy($id)
    {
        \Illuminate\Support\Facades\Gate::authorize('eliminar-registros');

        DB::table('registros_asistencia')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Registro eliminado correctamente.');
    }
}
