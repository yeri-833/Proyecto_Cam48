<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use App\Services\ImageUploadService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PersonalController extends Controller
{
    public function __construct(private TelegramService $telegramService) {}

    public function index()
    {
        Gate::authorize('personal.consultar');

        $personal = Personal::where('activo', 1)
            ->orderBy('nombre_completo')
            ->paginate(15);

        return Inertia::render('Personal/Index', [
            'personal' => $personal,
        ]);
    }

    public function store(Request $request, ImageUploadService $imageService)
    {
        Gate::authorize('personal.crear');

        $validated = $request->validate([
            'id' => 'required|string|unique:personal,id',
            'nombre_completo' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'rfc' => 'nullable|string|max:50',
            'categoria' => 'nullable|string|max:100',
            'funcion' => 'nullable|string|max:150',
            'plaza' => 'nullable|string|max:100',
            'grado_grupo' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:20',
            'telegram_chat_id' => 'nullable|string|max:100',
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'id.required' => 'El ID/Matrícula es obligatorio.',
            'id.unique' => 'Este ID ya está registrado en el sistema.',
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
        ]);

        $validated['activo'] = 1;

        if ($request->hasFile('foto_perfil')) {
            $validated['foto_perfil'] = $imageService->procesarYGuardar($request->file('foto_perfil'), 'fotos/personal/');
        }

        Personal::create($validated);

        return redirect()->back()->with('success', 'Personal registrado correctamente.');
    }

    public function update(Request $request, $id, ImageUploadService $imageService)
    {
        Gate::authorize('personal.actualizar');

        $validated = $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'rfc' => 'nullable|string|max:50',
            'categoria' => 'nullable|string|max:100',
            'funcion' => 'nullable|string|max:150',
            'plaza' => 'nullable|string|max:100',
            'grado_grupo' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:20',
            'telegram_chat_id' => 'nullable|string|max:100',
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
        ]);

        $personal = Personal::findOrFail($id);

        if ($request->hasFile('foto_perfil')) {
            if ($personal->foto_perfil && Storage::disk('public')->exists($personal->foto_perfil)) {
                Storage::disk('public')->delete($personal->foto_perfil);
            }

            $validated['foto_perfil'] = $imageService->procesarYGuardar($request->file('foto_perfil'), 'fotos/personal/');
        }

        $personal->update($validated);

        return redirect()->back()->with('success', 'Datos actualizados correctamente.');
    }

    public function destroy($id)
    {
        Gate::authorize('personal.eliminar');

        $personal = Personal::findOrFail($id);
        $personal->update(['activo' => 0]); // Borrado lógico

        return redirect()->back()->with('success', 'Personal dado de baja correctamente.');
    }

    public function enviarMensaje(Request $request, $id)
    {
        $validated = $request->validate([
            'mensaje' => 'required|string|max:1000',
        ]);

        $personal = Personal::findOrFail($id);

        if (empty($personal->telegram_chat_id)) {
            return redirect()->back()->withErrors([
                'mensaje' => 'Este docente no tiene un Chat ID de Telegram registrado.',
            ]);
        }

        $exito = $this->telegramService->enviarMensajeDirecto(
            $personal->telegram_chat_id,
            $personal->nombre_completo,
            $validated['mensaje']
        );

        if ($exito) {
            return redirect()->back()->with('success', 'Mensaje enviado exitosamente por Telegram al docente.');
        } else {
            return redirect()->back()->withErrors([
                'mensaje' => 'Hubo un error al intentar enviar el mensaje.',
            ]);
        }
    }

    public function enviarMensajeMasivo(Request $request)
    {
        $validated = $request->validate([
            'mensaje' => 'required|string|max:1000',
        ]);

        $personalConTelegram = Personal::whereNotNull('telegram_chat_id')->where('activo', 1)->get();
        $enviados = 0;

        foreach ($personalConTelegram as $p) {
            $exito = $this->telegramService->enviarMensajeDirecto(
                $p->telegram_chat_id,
                $p->nombre_completo,
                "📢 *Aviso Institucional (Personal)*\n\n".$validated['mensaje']
            );
            if ($exito) {
                $enviados++;
            }
        }

        return redirect()->back()->with('success', "Aviso masivo enviado a {$enviados} docentes vinculados.");
    }

    public function enviarInvitacionTelegram($id)
    {
        Gate::authorize('personal.actualizar');

        // 3.1 MEDIDA INMEDIATA: Bloquear generación de invitaciones por ID secuencial directo (OWASP A01)
        return redirect()->back()->with('warning', 'La generación de invitaciones mediante identificadores directos ha sido deshabilitada por políticas de privacidad escolar (v1.4.6). El protocolo criptográfico tokenizado está pendiente de migración institucional.');
    }
}
