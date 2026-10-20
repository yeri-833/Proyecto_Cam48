<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Tutor;
use App\Services\ImageUploadService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TutorController extends Controller
{
    public function __construct(private TelegramService $telegramService) {}

    /**
     * Muestra el directorio de tutores
     */
    public function index(Request $request)
    {
        Gate::authorize('tutores.consultar');

        $busqueda = $request->query('busqueda', '');

        $query = Tutor::with('alumno');

        if (! empty($busqueda)) {
            $query->where('t1_nombre', 'LIKE', "%{$busqueda}%")
                ->orWhere('t1_telefono', 'LIKE', "%{$busqueda}%")
                ->orWhereHas('alumno', function ($q) use ($busqueda) {
                    $q->where('nombre', 'LIKE', "%{$busqueda}%")
                        ->orWhere('apellido', 'LIKE', "%{$busqueda}%")
                        ->orWhere('matricula', 'LIKE', "%{$busqueda}%");
                });
        }

        $tutores = $query->orderBy('t1_nombre', 'asc')->paginate(15)->withQueryString();
        $alumnosDisponibles = Alumno::where('activo', 1)->select('matricula', 'nombre', 'apellido')->orderBy('nombre')->get();

        return Inertia::render('Tutores/Index', [
            'tutores' => $tutores,
            'alumnos' => $alumnosDisponibles,
            'filtros' => ['busqueda' => $busqueda],
        ]);
    }

    /**
     * Guarda o actualiza un tutor
     */
    public function store(Request $request, ImageUploadService $imageService)
    {
        Gate::authorize('tutores.crear');

        $validated = $request->validate([
            'matricula_alumno' => 'required|exists:alumnos,matricula',
            't1_nombre' => 'required|string|max:255',
            't1_telefono' => 'required|string|max:20',
            't1_email' => 'nullable|email|max:255',
            't1_telegram_chat_id' => 'nullable|string|max:100',
            't1_foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            't1_nombre' => strtoupper($validated['t1_nombre']),
            't1_telefono' => $validated['t1_telefono'],
            't1_telegram_chat_id' => $validated['t1_telegram_chat_id'] ?? null,
        ];

        if ($request->filled('t1_email')) {
            $data['t1_email'] = $validated['t1_email'];
        }

        // Verificar si el alumno ya tiene un tutor
        $tutorExistente = Tutor::where('matricula_alumno', $validated['matricula_alumno'])->first();

        if ($request->hasFile('t1_foto_perfil')) {
            if ($tutorExistente && $tutorExistente->t1_foto_perfil && Storage::disk('public')->exists($tutorExistente->t1_foto_perfil)) {
                Storage::disk('public')->delete($tutorExistente->t1_foto_perfil);
            }

            $data['t1_foto_perfil'] = $imageService->procesarYGuardar($request->file('t1_foto_perfil'), 'fotos/tutores/');
        }

        if ($tutorExistente) {
            $tutorExistente->update($data);
            $msg = 'Información del tutor actualizada.';
        } else {
            $data['matricula_alumno'] = $validated['matricula_alumno'];
            Tutor::create($data);
            $msg = 'Tutor asignado correctamente al alumno.';
        }

        return redirect()->route('tutores.index')->with('success', $msg);
    }

    /**
     * Elimina el vínculo de un tutor
     */
    public function destroy($id)
    {
        Gate::authorize('tutores.eliminar');

        $tutor = Tutor::findOrFail($id);
        $tutor->delete();

        return redirect()->route('tutores.index')->with('success', 'Registro del tutor eliminado correctamente.');
    }

    /**
     * Envia un mensaje directo al tutor
     */
    public function enviarMensaje(Request $request, $id)
    {
        $validated = $request->validate([
            'mensaje' => 'required|string|max:1000',
        ]);

        $tutor = Tutor::findOrFail($id);

        if (empty($tutor->t1_telegram_chat_id)) {
            return redirect()->back()->withErrors([
                'mensaje' => 'Este tutor no tiene un Chat ID de Telegram registrado. No se puede enviar el mensaje.',
            ]);
        }

        $exito = $this->telegramService->enviarMensajeDirecto(
            $tutor->t1_telegram_chat_id,
            $tutor->t1_nombre,
            $validated['mensaje']
        );

        if ($exito) {
            return redirect()->back()->with('success', 'Mensaje enviado exitosamente al padre por Telegram.');
        } else {
            return redirect()->back()->withErrors([
                'mensaje' => 'Hubo un error de conexión al intentar enviar el mensaje.',
            ]);
        }
    }

    /**
     * Envia el enlace mágico de vinculación de Telegram por correo
     */
    public function enviarInvitacionTelegram($id)
    {
        Gate::authorize('tutores.actualizar');

        // 3.1 MEDIDA INMEDIATA: Bloquear generación de invitaciones por ID secuencial directo (OWASP A01)
        return redirect()->back()->with('warning', 'La generación de invitaciones mediante identificadores directos ha sido deshabilitada por políticas de privacidad escolar (v1.4.6). El protocolo criptográfico tokenizado está pendiente de migración institucional.');
    }
}
