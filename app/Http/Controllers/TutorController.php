<?php

namespace App\Http\Controllers;

use App\Mail\InvitacionTelegramMail;
use App\Models\Alumno;
use App\Models\Tutor;
use App\Services\ImageUploadService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            't1_email' => $validated['t1_email'] ?? null,
            't1_telegram_chat_id' => $validated['t1_telegram_chat_id'] ?? null,
        ];

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
        $tutor = Tutor::findOrFail($id);

        if (empty($tutor->t1_email)) {
            return redirect()->back()->withErrors([
                'mensaje' => 'El tutor no tiene un correo electrónico registrado para enviar la invitación.',
            ]);
        }

        // Generamos el enlace mágico
        $botUsername = config('telegram.bot_username');
        $codigoMagico = 'tutor_'.$tutor->id;
        $enlaceMagico = "https://t.me/{$botUsername}?start={$codigoMagico}";

        try {
            Mail::to($tutor->t1_email)->send(new InvitacionTelegramMail($tutor, $enlaceMagico));

            return redirect()->back()->with('success', 'Invitación enviada por correo electrónico correctamente.');
        } catch (\Exception $e) {
            Log::error('Error enviando invitación Telegram a tutor: '.$e->getMessage(), ['id' => $id]);

            return redirect()->back()->withErrors([
                'mensaje' => 'Hubo un problema al enviar el correo. Intenta de nuevo más tarde.',
            ]);
        }
    }
}
