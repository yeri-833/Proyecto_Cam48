<?php

namespace App\Http\Controllers;

use App\Models\Tutor;
use App\Models\Alumno;
use App\Services\TelegramService;
use App\Mail\InvitacionTelegramMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class TutorController extends Controller
{
    /**
     * Muestra el directorio de tutores
     */
    public function index(Request $request)
    {
        $busqueda = $request->query('busqueda', '');

        $query = Tutor::with('alumno');

        if (!empty($busqueda)) {
            $query->where('t1_nombre', 'LIKE', "%{$busqueda}%")
                  ->orWhere('t1_telefono', 'LIKE', "%{$busqueda}%")
                  ->orWhereHas('alumno', function($q) use ($busqueda) {
                      $q->where('nombre', 'LIKE', "%{$busqueda}%")
                        ->orWhere('apellido', 'LIKE', "%{$busqueda}%")
                        ->orWhere('matricula', 'LIKE', "%{$busqueda}%");
                  });
        }

        $tutores = $query->orderBy('t1_nombre', 'asc')->paginate(15)->withQueryString();
        $alumnosDisponibles = Alumno::where('activo', 1)->orderBy('nombre')->get();

        return Inertia::render('Tutores/Index', [
            'tutores' => $tutores,
            'alumnos' => $alumnosDisponibles,
            'filtros' => ['busqueda' => $busqueda]
        ]);
    }

    /**
     * Guarda o actualiza un tutor
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'matricula_alumno' => 'required|exists:alumnos,matricula',
            't1_nombre' => 'required|string|max:255',
            't1_telefono' => 'required|string|max:20',
            't1_email' => 'nullable|email|max:255',
            't1_telegram_chat_id' => 'nullable|string|max:100',
            't1_foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $data = [
            't1_nombre' => strtoupper($validated['t1_nombre']),
            't1_telefono' => $validated['t1_telefono'],
            't1_email' => $validated['t1_email'] ?? null,
            't1_telegram_chat_id' => $validated['t1_telegram_chat_id'] ?? null
        ];

        // Verificar si el alumno ya tiene un tutor
        $tutorExistente = Tutor::where('matricula_alumno', $validated['matricula_alumno'])->first();

        if ($request->hasFile('t1_foto_perfil')) {
            if ($tutorExistente && $tutorExistente->t1_foto_perfil && Storage::disk('public')->exists($tutorExistente->t1_foto_perfil)) {
                Storage::disk('public')->delete($tutorExistente->t1_foto_perfil);
            }
            
            $manager = new ImageManager(new Driver());
            $image = $manager->read($request->file('t1_foto_perfil')->getRealPath());
            $image->scaleDown(width: 800);
            $encoded = $image->toJpeg(quality: 80);
            
            $filename = 'fotos/tutores/' . uniqid() . '.jpg';
            Storage::disk('public')->put($filename, $encoded->toString());
            
            $data['t1_foto_perfil'] = $filename;
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
        \Illuminate\Support\Facades\Gate::authorize('eliminar-registros');

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
            'mensaje' => 'required|string|max:1000'
        ]);

        $tutor = Tutor::findOrFail($id);

        if (empty($tutor->t1_telegram_chat_id)) {
            return redirect()->back()->withErrors([
                'mensaje' => 'Este tutor no tiene un Chat ID de Telegram registrado. No se puede enviar el mensaje.'
            ]);
        }

        $tgService = new TelegramService();
        $exito = $tgService->enviarMensajeDirecto(
            $tutor->t1_telegram_chat_id,
            $tutor->t1_nombre,
            $validated['mensaje']
        );

        if ($exito) {
            return redirect()->back()->with('success', 'Mensaje enviado exitosamente al padre por Telegram.');
        } else {
            return redirect()->back()->withErrors([
                'mensaje' => 'Hubo un error de conexión al intentar enviar el mensaje.'
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
                'mensaje' => 'El tutor no tiene un correo electrónico registrado para enviar la invitación.'
            ]);
        }

        // Generamos el enlace mágico
        $botUsername = 'cam48_notificaciones_bot'; // Reemplazar con el nombre real de tu bot o sacarlo del .env
        $codigoMagico = 'tutor_' . $tutor->id;
        $enlaceMagico = "https://t.me/{$botUsername}?start={$codigoMagico}";

        try {
            Mail::to($tutor->t1_email)->send(new InvitacionTelegramMail($tutor, $enlaceMagico));
            return redirect()->back()->with('success', 'Invitación enviada por correo electrónico correctamente.');
        } catch (\Exception $e) {
            Log::error('Error enviando invitación Telegram a tutor: ' . $e->getMessage(), ['id' => $id]);
            return redirect()->back()->withErrors([
                'mensaje' => 'Hubo un problema al enviar el correo. Intenta de nuevo más tarde.'
            ]);
        }
    }

    /**
     * Sincronizador silencioso para detectar nuevas vinculaciones de Telegram
     */
    public function syncTelegram()
    {
        $token = config('telegram.bot_token');
        if (empty($token)) {
            return response()->json(['status' => 'no_token']);
        }

        $offset = \Illuminate\Support\Facades\Cache::get('telegram_bot_offset', 0);
        $vinculadosNuevos = [];

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 1
                ]);

            if ($response->successful()) {
                $updates = $response->json('result');
                
                foreach ($updates as $update) {
                    $offset = max($offset, $update['update_id'] + 1);
                    
                    if (isset($update['message']['text'])) {
                        $text = $update['message']['text'];
                        $chatId = $update['message']['chat']['id'];
                        
                        if (preg_match('/^\/start tutor_(\d+)$/', $text, $matches)) {
                            $tutorId = $matches[1];
                            $tutor = Tutor::find($tutorId);
                            
                            if ($tutor && empty($tutor->t1_telegram_chat_id)) {
                                $tutor->t1_telegram_chat_id = $chatId;
                                $tutor->save();
                                
                                $vinculadosNuevos[] = $tutor->t1_nombre;

                                // Enviar bienvenida y enlace al grupo
                                $mensaje = "🎉 *¡Vinculación Exitosa!*\n\n"
                                         . "Hola *{$tutor->t1_nombre}*, tu cuenta ha sido conectada al sistema del CAM #48.\n\n"
                                         . "A partir de este momento, recibirás mensajes directos por aquí.\n\n"
                                         . "📢 *¡Paso Final! Únete al grupo oficial:*\n"
                                         . "Para recibir los avisos y circulares generales de la escuela, por favor únete a nuestro canal tocando el siguiente enlace:\n"
                                         . "👉 https://t.me/cam48comunicados";
                                
                                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                                    'chat_id' => $chatId,
                                    'text' => $mensaje,
                                    'parse_mode' => 'Markdown'
                                ]);
                            }
                        } elseif (preg_match('/^\/start personal_(\d+)$/', $text, $matches)) {
                            $personalId = $matches[1];
                            $personal = \App\Models\Personal::find($personalId);
                            
                            if ($personal && empty($personal->telegram_chat_id)) {
                                $personal->telegram_chat_id = $chatId;
                                $personal->save();
                                
                                $vinculadosNuevos[] = "Prof. " . $personal->nombre_completo;

                                $mensaje = "🎉 *¡Vinculación Exitosa!*\n\n"
                                         . "Hola *{$personal->nombre_completo}*, tu cuenta ha sido conectada al portal del Personal del CAM #48.\n\n"
                                         . "Recibirás avisos institucionales, notificaciones de juntas y circulares directamente en este chat.\n\n"
                                         . "📢 *Recuerda unirte al grupo del personal si no lo has hecho:* (Consulta el enlace con Dirección).";
                                
                                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                                    'chat_id' => $chatId,
                                    'text' => $mensaje,
                                    'parse_mode' => 'Markdown'
                                ]);
                            }
                        }
                    }
                }
                
                // Actualizamos el offset para no volver a leer los mismos mensajes
                \Illuminate\Support\Facades\Cache::put('telegram_bot_offset', $offset, now()->addDays(1));
            }
        } catch (\Exception $e) {
            Log::error('Error en syncTelegram: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error de sincronización.']);
        }

        return response()->json([
            'status' => 'ok',
            'vinculados' => $vinculadosNuevos
        ]);
    }
}
