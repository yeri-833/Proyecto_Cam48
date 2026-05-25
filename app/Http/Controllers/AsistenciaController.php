<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Personal;
use App\Models\Tutor;
use App\Models\RegistroAsistencia;
use App\Services\WhatsAppService;
use App\Services\TelegramService;
use App\Jobs\NotificarAsistenciaJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class AsistenciaController extends Controller
{
    /**
     * Renderiza la vista de la terminal de accesos QR
     */
    public function escaner()
    {
        return Inertia::render('Asistencia/Escaner');
    }

    /**
     * Procesa la lectura del código QR o código de barras
     */
    public function procesarQr(Request $request)
    {
        $qrData = $request->input('qr_data');

        if (empty($qrData)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Código QR ilegible o vacío'
            ]);
        }

        // Lógica para detectar el ID de la persona y su rol
        $idPersona = null;
        $rol = 'Alumno'; // Por defecto
        $signature = null;

        // Caso A: JSON string
        if (is_string($qrData)) {
            $decoded = json_decode($qrData, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                $idPersona = trim($decoded['id']);
                $rol = $decoded['rol'] ?? 'Alumno';
                $signature = $decoded['signature'] ?? null;
            } else {
                // Caso B: Texto plano (Ya no se permite bajo el nuevo esquema de seguridad estricto)
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Formato de QR no soportado o obsoleto. Por favor, genere una nueva credencial.'
                ]);
            }
        } elseif (is_array($qrData)) {
            $idPersona = trim($qrData['id'] ?? '');
            $rol = $qrData['rol'] ?? 'Alumno';
            $signature = $qrData['signature'] ?? null;
        }

        if (empty($idPersona)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'ID no válido en el código QR'
            ]);
        }

        // Validación de Firma HMAC Anti-clonación
        $expectedSignature = hash_hmac('sha256', $idPersona . $rol, config('app.key'));
        
        if (empty($signature) || !hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Código QR inválido o falsificado (Firma incorrecta)'
            ]);
        }

        try {
            $nombreCompleto = '';
            $fechaHoy = Carbon::today()->toDateString();
            $horaActual = Carbon::now()->toTimeString();

            // Buscar en Alumnos
            if ($rol === 'Alumno' || $rol === 'Tutor') {
                $alumno = Alumno::where('matricula', $idPersona)->first();
                
                if ($alumno) {
                    if (!$alumno->activo) {
                        return response()->json([
                            'status' => 'error',
                            'msg' => 'El alumno está inactivo o de baja'
                        ]);
                    }
                    $nombreCompleto = $alumno->nombre_completo;
                    $rol = 'Alumno';
                } else {
                    // Buscar en Tutores si no se halló como Alumno
                    $tutor = Tutor::where('t1_telefono', $idPersona)
                        ->orWhere('matricula_alumno', $idPersona)
                        ->first();
                    
                    if ($tutor) {
                        $nombreCompleto = strtoupper($tutor->t1_nombre . ' ' . $tutor->t1_apellido);
                        $rol = 'Tutor';
                    }
                }
            } else {
                // Buscar en Personal
                $personal = Personal::where('id', $idPersona)->first();
                if ($personal) {
                    if (!$personal->activo) {
                        return response()->json([
                            'status' => 'error',
                            'msg' => 'El trabajador está inactivo o de baja'
                        ]);
                    }
                    $nombreCompleto = strtoupper($personal->nombre_completo);
                }
            }

            if (empty($nombreCompleto)) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'El código no existe en el sistema',
                    'persona' => 'ID: ' . $idPersona
                ]);
            }

            // Cooldown de 5 minutos para evitar duplicados accidentales
            $ultimoRegistro = RegistroAsistencia::where('id_persona', $idPersona)
                ->where('fecha', $fechaHoy)
                ->orderBy('hora', 'desc')
                ->first();

            if ($ultimoRegistro) {
                $ultimoTiempo = Carbon::createFromFormat('Y-m-d H:i:s', $fechaHoy . ' ' . $ultimoRegistro->hora);
                if (Carbon::now()->diffInSeconds($ultimoTiempo) < 300) {
                    return response()->json([
                        'status' => 'warning',
                        'msg' => 'Pase registrado recientemente.',
                        'persona' => $nombreCompleto
                    ]);
                }
            }

            // Registrar la asistencia
            RegistroAsistencia::create([
                'id_persona' => $idPersona,
                'rol' => $rol,
                'fecha' => $fechaHoy,
                'hora' => $horaActual
            ]);

            // =========== ALERTAS AUTOMÁTICAS (WHATSAPP + TELEGRAM) ===========
            if ($rol === 'Alumno' && isset($alumno) && $alumno->tutores) {
                $horaFormateada = Carbon::now()->format('h:i A');
                $tipoPase = Carbon::now()->hour < 13 ? 'ENTRADA' : 'SALIDA';

                NotificarAsistenciaJob::dispatch(
                    $alumno, 
                    $tipoPase, 
                    $horaFormateada, 
                    $nombreCompleto
                );
                
                $msgResponse = 'Asistencia registrada y avisos enviados';
            } else {
                $msgResponse = 'Asistencia registrada';
            }
            // ==========================================

            return response()->json([
                'status' => 'success',
                'msg' => $msgResponse,
                'persona' => $nombreCompleto,
                'hora' => Carbon::now()->format('h:i A')
            ]);

        } catch (\Exception $e) {
            Log::error('Error en procesarQr: ' . $e->getMessage(), ['id' => $idPersona ?? 'N/A']);
            return response()->json([
                'status' => 'error',
                'msg' => 'Ocurrió un error inesperado. Intenta de nuevo.'
            ]);
        }
    }

    /**
     * Valida la contraseña del usuario para salir del modo Kiosco
     */
    public function desbloquearKiosco(Request $request)
    {
        $password = $request->input('password');

        if (empty($password)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Contraseña vacía'
            ]);
        }

        $user = auth()->user();

        if ($user && Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'success'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'msg' => 'Contraseña incorrecta'
        ]);
    }
}
