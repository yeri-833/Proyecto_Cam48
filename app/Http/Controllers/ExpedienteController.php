<?php

namespace App\Http\Controllers;

use App\Models\ArchivoSistema;
use App\Models\Alumno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Inertia\Inertia;

class ExpedienteController extends Controller
{
    /**
     * Muestra la vista del Expediente Digital
     */
    public function index(Request $request)
    {
        $busqueda = $request->query('busqueda', '');
        
        $query = ArchivoSistema::with('alumno');
        
        if (!empty($busqueda)) {
            $query->where('nombre_original', 'LIKE', "%{$busqueda}%")
                  ->orWhere('matricula', 'LIKE', "%{$busqueda}%")
                  ->orWhereHas('alumno', function($q) use ($busqueda) {
                      $q->where('nombre', 'LIKE', "%{$busqueda}%")
                        ->orWhere('apellido', 'LIKE', "%{$busqueda}%");
                  });
        }
        
        $archivos = $query->orderBy('fecha_subida', 'desc')->get()->map(function($archivo) {
            $archivo->url = asset('storage/expedientes/' . $archivo->nombre_servidor);
            $archivo->fecha_formateada = Carbon::parse($archivo->fecha_subida)->format('d/m/Y h:i A');
            // Convertir bytes a formato legible
            $bytes = $archivo->tamano_bytes;
            $unidades = ['B', 'KB', 'MB', 'GB'];
            $i = 0;
            while ($bytes >= 1024 && $i < count($unidades) - 1) {
                $bytes /= 1024;
                $i++;
            }
            $archivo->tamano_legible = round($bytes, 2) . ' ' . $unidades[$i];
            return $archivo;
        });

        $alumnos = Alumno::where('activo', 1)->orderBy('nombre')->get();

        return Inertia::render('Expedientes/Index', [
            'archivos' => $archivos,
            'alumnos' => $alumnos,
            'filtros' => ['busqueda' => $busqueda]
        ]);
    }

    /**
     * Sube un nuevo documento al expediente
     */
    public function store(Request $request)
    {
        $request->validate([
            'matricula' => 'required|exists:alumnos,matricula',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,zip,rar|max:10240', // Max 10MB
        ], [
            'archivo.mimes' => 'Solo se permiten archivos PDF, Imágenes, Word o ZIP/RAR.',
            'archivo.max' => 'El archivo no debe pesar más de 10MB.'
        ]);

        $file = $request->file('archivo');
        $ext = $file->getClientOriginalExtension();
        $nombre_servidor = $request->matricula . "_" . time() . "." . strtolower($ext);

        // Guardar archivo físicamente
        $file->storeAs('expedientes', $nombre_servidor, 'public');

        // Registro en BD
        ArchivoSistema::create([
            'matricula' => $request->matricula,
            'nombre_original' => $file->getClientOriginalName(),
            'nombre_servidor' => $nombre_servidor,
            'tipo_mime' => $file->getMimeType(),
            'tamano_bytes' => $file->getSize(),
            'fecha_subida' => Carbon::now()
        ]);

        return redirect()->back()->with('success', 'Documento subido correctamente al expediente.');
    }

    /**
     * Elimina un archivo físicamente y de la BD
     */
    public function destroy($id)
    {
        $archivo = ArchivoSistema::findOrFail($id);
        
        if (Storage::disk('public')->exists('expedientes/' . $archivo->nombre_servidor)) {
            Storage::disk('public')->delete('expedientes/' . $archivo->nombre_servidor);
        }
        
        $archivo->delete();

        return redirect()->back()->with('success', 'Documento eliminado correctamente.');
    }
}
