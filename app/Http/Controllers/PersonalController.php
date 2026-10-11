<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use App\Services\TelegramService;

class PersonalController extends Controller
{
    public function __construct(private TelegramService $telegramService)
    {
    }
    public function index()
    {
        $personal = Personal::where('activo', 1)
            ->orderBy('nombre_completo')
            ->paginate(15);

        return Inertia::render('Personal/Index', [
            'personal' => $personal
        ]);
    }

    public function store(Request $request)
    {
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
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('foto_perfil')->getRealPath());
            $image->scaleDown(width: 800);
            $encoded = $image->toJpeg(quality: 80);
            
            $filename = 'fotos/personal/' . uniqid() . '.jpg';
            Storage::disk('public')->put($filename, $encoded->toString());
            
            $validated['foto_perfil'] = $filename;
        }

        Personal::create($validated);

        return redirect()->back()->with('success', 'Personal registrado correctamente.');
    }

    public function update(Request $request, $id)
    {
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
            
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('foto_perfil')->getRealPath());
            $image->scaleDown(width: 800);
            $encoded = $image->toJpeg(quality: 80);
            
            $filename = 'fotos/personal/' . uniqid() . '.jpg';
            Storage::disk('public')->put($filename, $encoded->toString());
            
            $validated['foto_perfil'] = $filename;
        }

        $personal->update($validated);

        return redirect()->back()->with('success', 'Datos actualizados correctamente.');
    }

    public function destroy($id)
    {
        \Illuminate\Support\Facades\Gate::authorize('eliminar-registros');

        $personal = Personal::findOrFail($id);
        $personal->update(['activo' => 0]); // Borrado lógico

        return redirect()->back()->with('success', 'Personal dado de baja correctamente.');
    }

    public function enviarMensaje(Request $request, $id)
    {
        $validated = $request->validate([
            'mensaje' => 'required|string|max:1000'
        ]);

        $personal = Personal::findOrFail($id);

        if (empty($personal->telegram_chat_id)) {
            return redirect()->back()->withErrors([
                'mensaje' => 'Este docente no tiene un Chat ID de Telegram registrado.'
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
                'mensaje' => 'Hubo un error al intentar enviar el mensaje.'
            ]);
        }
    }

    public function enviarMensajeMasivo(Request $request)
    {
        $validated = $request->validate([
            'mensaje' => 'required|string|max:1000'
        ]);

        $personalConTelegram = Personal::whereNotNull('telegram_chat_id')->where('activo', 1)->get();
        $enviados = 0;

        foreach ($personalConTelegram as $p) {
            $exito = $this->telegramService->enviarMensajeDirecto(
                $p->telegram_chat_id,
                $p->nombre_completo,
                "📢 *Aviso Institucional (Personal)*\n\n" . $validated['mensaje']
            );
            if ($exito) $enviados++;
        }

        return redirect()->back()->with('success', "Aviso masivo enviado a {$enviados} docentes vinculados.");
    }

    public function enviarInvitacionTelegram($id)
    {
        $personal = Personal::findOrFail($id);
        
        $botUsername = config('telegram.bot_username');
        $codigoMagico = 'personal_' . $personal->id;
        $enlaceMagico = "https://t.me/{$botUsername}?start={$codigoMagico}";

        if (!empty($personal->email)) {
            try {
                // Usaremos un MailGenérico o adaptaremos el de Tutor
                // Para simplificar, le enviaremos el de Tutor simulando la estructura
                $dummyTutor = (object) ['t1_nombre' => 'Profesor(a) ' . $personal->nombre_completo];
                \Illuminate\Support\Facades\Mail::to($personal->email)->send(new \App\Mail\InvitacionTelegramMail($dummyTutor, $enlaceMagico));
                
                return redirect()->back()->with('success', 'Invitación enviada por correo electrónico al docente exitosamente.');
            } catch (\Exception $e) {
                Log::error('Error enviando invitación Telegram a personal: ' . $e->getMessage(), ['id' => $id]);
                return redirect()->back()->withErrors([
                    'mensaje' => 'No se pudo enviar el correo. Puedes enviarle este enlace de forma manual: ' . $enlaceMagico
                ]);
            }
        }

        return redirect()->back()->with('success', 'Comparte este enlace con el profesor por WhatsApp para vincular su cuenta: ' . $enlaceMagico);
    }
}
