<?php

namespace App\Http\Controllers;

use App\Jobs\NotificarAsistenciaJob;
use App\Models\Alumno;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AsistenciaController extends Controller
{
    /**
     * Renderiza la vista de la terminal de accesos QR
     */
    public function escaner()
    {
        Gate::authorize('asistencia.escanear');

        return Inertia::render('Asistencia/Escaner');
    }

    /**
     * Procesa la lectura del código QR o código de barras
     */
    public function procesarQr(Request $request)
    {
        Gate::authorize('asistencia.escanear');

        $qrData = $request->input('qr_data');

        if (empty($qrData)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Código QR ilegible o vacío',
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
                    'msg' => 'Formato de QR no soportado o obsoleto. Por favor, genere una nueva credencial.',
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
                'msg' => 'ID no válido en el código QR',
            ]);
        }

        // Validación de Firma HMAC Anti-clonación
        $expectedSignature = hash_hmac('sha256', $idPersona.$rol, config('app.key'));

        if (empty($signature) || ! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Código QR inválido o falsificado (Firma incorrecta)',
            ]);
        }

        try {
            $nombreCompleto = '';
            $personaModel = null;
            $fechaHoy = Carbon::today()->toDateString();
            $horaActual = Carbon::now()->toTimeString();

            // Buscar según el rol especificado en la credencial de forma estrictamente aislada
            if ($rol === 'Alumno') {
                $alumno = Alumno::where('matricula', $idPersona)->first();
                if ($alumno) {
                    if (! $alumno->activo) {
                        return response()->json([
                            'status' => 'error',
                            'msg' => 'El alumno está inactivo o de baja',
                        ]);
                    }
                    $nombreCompleto = $alumno->nombre_completo;
                    $personaModel = $alumno;
                }
            } elseif ($rol === 'Tutor') {
                // Búsqueda estricta por ID real de la tabla tutores
                $tutor = is_numeric($idPersona) ? Tutor::find((int) $idPersona) : null;
                if ($tutor) {
                    $nombreCompleto = $tutor->nombre_completo;
                    $personaModel = $tutor;
                }
            } else {
                // Buscar exclusivamente en Personal por id numérico
                $personal = is_numeric($idPersona) ? Personal::find((int) $idPersona) : Personal::where('id', $idPersona)->first();
                if ($personal) {
                    if (! $personal->activo) {
                        return response()->json([
                            'status' => 'error',
                            'msg' => 'El trabajador está inactivo o de baja',
                        ]);
                    }
                    $nombreCompleto = $personal->nombre_completo;
                    $personaModel = $personal;
                }
            }

            if ($personaModel === null || $nombreCompleto === '') {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'El código no existe en el sistema',
                    'persona' => 'ID: '.$idPersona,
                ]);
            }

            // Cooldown de 5 minutos para evitar duplicados accidentales
            /** @var RegistroAsistencia|null $ultimoRegistro */
            $ultimoRegistro = $personaModel->asistencias()
                ->whereDate('fecha', $fechaHoy)
                ->latest('id')
                ->first();

            if ($ultimoRegistro) {
                try {
                    $ultimoTiempo = Carbon::parse($fechaHoy.' '.$ultimoRegistro->hora);

                    if (abs(Carbon::now()->diffInSeconds($ultimoTiempo)) < 300) {
                        return response()->json([
                            'status' => 'warning',
                            'msg' => 'Pase registrado recientemente.',
                            'persona' => $nombreCompleto,
                        ]);
                    }
                } catch (\Exception $exTime) {
                    Log::warning('Error calculando cooldown de asistencia: '.$exTime->getMessage());
                }
            }

            // Registrar la asistencia polimórfica (y conservar campos heredados condicionalmente para compatibilidad)
            $asistenciaData = [
                'fecha' => $fechaHoy,
                'hora' => $horaActual,
            ];

            if (Schema::hasColumn('registros_asistencia', 'modo_registro')) {
                $asistenciaData['modo_registro'] = 'QR';
            }
            if (Schema::hasColumn('registros_asistencia', 'id_persona')) {
                $asistenciaData['id_persona'] = (string) $idPersona;
            }
            if (Schema::hasColumn('registros_asistencia', 'rol')) {
                $asistenciaData['rol'] = $rol;
            }

            $personaModel->asistencias()->create($asistenciaData);

            // =========== ALERTAS AUTOMÁTICAS (WHATSAPP + TELEGRAM) ===========
            if ($rol === 'Alumno' && isset($alumno) && $alumno->tutor) {
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
                'hora' => Carbon::now()->format('h:i A'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error en procesarQr: '.$e->getMessage(), ['id' => (string) $idPersona]);

            return response()->json([
                'status' => 'error',
                'msg' => 'Ocurrió un error inesperado. Intenta de nuevo.',
            ]);
        }
    }

    /**
     * Valida la contraseña del usuario para salir del modo Kiosco
     */
    public function desbloquearKiosco(Request $request)
    {
        Gate::authorize('asistencia.escanear');

        $password = $request->input('password');

        if (empty($password)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Contraseña vacía',
            ]);
        }

        $user = auth()->user();

        if ($user && Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'success',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'msg' => 'Contraseña incorrecta',
        ]);
    }
}
