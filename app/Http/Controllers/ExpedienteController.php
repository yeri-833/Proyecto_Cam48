<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\ArchivoSistema;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpedienteController extends Controller
{
    /**
     * Muestra la vista del Expediente Digital con filtrado estricto según el alcance del usuario (OWASP A01).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ArchivoSistema::class);

        /** @var User $user */
        $user = $request->user();
        $busqueda = $request->query('busqueda', '');

        $query = ArchivoSistema::with('alumno');
        $alumnosQuery = Alumno::where('activo', 1)->accessibleTo($user);

        // Los docentes solo visualizan alumnos y expedientes de su grupo asignado
        if (! $user->isAdmin()) {
            $query->whereHas('alumno', fn (Builder $alumnos): Builder => $alumnos->accessibleTo($user));
        }

        if (! empty($busqueda)) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre_original', 'LIKE', "%{$busqueda}%")
                    ->orWhere('matricula', 'LIKE', "%{$busqueda}%")
                    ->orWhereHas('alumno', function ($sub) use ($busqueda) {
                        $sub->where('nombre', 'LIKE', "%{$busqueda}%")
                            ->orWhere('apellido', 'LIKE', "%{$busqueda}%");
                    });
            });
        }

        $archivos = $query->orderBy('fecha_subida', 'desc')->paginate(15)->through(function ($archivo) {
            $archivo->url = route('expedientes.ver', $archivo->id);
            $archivo->download_url = route('expedientes.descargar', $archivo->id);
            $archivo->fecha_formateada = Carbon::parse($archivo->fecha_subida)->format('d/m/Y h:i A');

            $bytes = $archivo->tamano_bytes ?? 0;
            $unidades = ['B', 'KB', 'MB', 'GB'];
            $i = 0;
            while ($bytes >= 1024 && $i < count($unidades) - 1) {
                $bytes /= 1024;
                $i++;
            }
            $archivo->tamano_legible = round($bytes, 2).' '.$unidades[$i];

            return $archivo;
        });

        // El selector de alumnos también se limita estrictamente al alcance del docente
        $alumnos = $alumnosQuery->select('matricula', 'nombre', 'apellido')->orderBy('nombre')->get();

        return Inertia::render('Expedientes/Index', [
            'archivos' => $archivos,
            'alumnos' => $alumnos,
            'filtros' => ['busqueda' => $busqueda],
        ]);
    }

    /**
     * Sube un nuevo documento confidencial al expediente en almacenamiento privado.
     * Comprueba autorización respecto al alumno de destino (OWASP A01).
     */
    public function store(Request $request)
    {
        Gate::authorize('create', ArchivoSistema::class);

        $request->validate([
            'matricula' => 'required|exists:alumnos,matricula',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,zip,rar|max:10240', // Max 10MB
        ], [
            'archivo.mimes' => 'Solo se permiten archivos PDF, Imágenes, Word o ZIP/RAR.',
            'archivo.max' => 'El archivo no debe pesar más de 10MB.',
        ]);

        /** @var Alumno $alumno */
        $alumno = Alumno::where('matricula', $request->input('matricula'))->firstOrFail();

        // Validar que el usuario tenga autorización sobre el alumno de destino
        Gate::authorize('create', [ArchivoSistema::class, $alumno]);

        $file = $request->file('archivo');
        $ext = strtolower($file->getClientOriginalExtension());

        // Verificación estricta de extensiones permitidas
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip', 'rar'];
        if (! in_array($ext, $allowedExtensions)) {
            return redirect()->back()->withErrors(['archivo' => 'Tipo de archivo no permitido por políticas de seguridad.']);
        }

        // Generar nombre aleatorio criptográficamente seguro (no predecible)
        $nombre_servidor = bin2hex(random_bytes(16)).'_'.time().'.'.$ext;

        // Guardar físicamente en el disco PRIVADO (local)
        $file->storeAs('expedientes', $nombre_servidor, 'local');

        // Registro en BD
        ArchivoSistema::create([
            'matricula' => $alumno->matricula,
            'nombre_original' => $file->getClientOriginalName(),
            'nombre_servidor' => $nombre_servidor,
            'tipo_mime' => $file->getMimeType(),
            'tamano_bytes' => $file->getSize(),
            'fecha_subida' => Carbon::now(),
        ]);

        return redirect()->back()->with('success', 'Documento guardado de forma segura en el expediente digital.');
    }

    /**
     * Muestra/transmite de forma segura el documento en el navegador (iframe/visor).
     */
    public function ver($id)
    {
        /** @var ArchivoSistema $archivo */
        $archivo = ArchivoSistema::with('alumno')->findOrFail($id);
        Gate::authorize('view', $archivo);

        $safeName = basename($archivo->nombre_servidor);
        $path = 'expedientes/'.$safeName;

        // 1. Buscar en almacenamiento privado moderno (local)
        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path), [
                'Content-Type' => $archivo->tipo_mime ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($archivo->nombre_original).'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // 2. Compatibilidad con archivos legacy en public sin romperlos
        if (Storage::disk('public')->exists($path)) {
            return response()->file(Storage::disk('public')->path($path), [
                'Content-Type' => $archivo->tipo_mime ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($archivo->nombre_original).'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404, 'El documento solicitado no se encuentra en el servidor.');
    }

    /**
     * Descarga de forma segura el documento del expediente.
     */
    public function descargar($id)
    {
        /** @var ArchivoSistema $archivo */
        $archivo = ArchivoSistema::with('alumno')->findOrFail($id);
        Gate::authorize('view', $archivo);

        $safeName = basename($archivo->nombre_servidor);
        $path = 'expedientes/'.$safeName;

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $archivo->nombre_original);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $archivo->nombre_original);
        }

        abort(404, 'El documento solicitado no se encuentra en el servidor.');
    }

    /**
     * Elimina un archivo físicamente de ambos discos y de la BD.
     */
    public function destroy($id)
    {
        /** @var ArchivoSistema $archivo */
        $archivo = ArchivoSistema::findOrFail($id);
        Gate::authorize('delete', $archivo);
        $path = 'expedientes/'.$archivo->nombre_servidor;

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $archivo->delete();

        return redirect()->back()->with('success', 'Documento eliminado correctamente.');
    }
}
