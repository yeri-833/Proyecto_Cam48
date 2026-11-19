<?php

namespace App\Http\Controllers;

use App\Jobs\NotificarAsistenciaJob;
use App\Models\Alumno;
use App\Models\Evento;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
     * Cache en memoria de las columnas de la tabla registros_asistencia para evitar
     * consultas repetitivas de introspección de metadatos en cada escaneo QR.
     *
     * @var array<string, bool>|null
     */
    private static ?array $columnasAsistenciaCache = null;

    /**
     * Comprueba de forma eficiente y en memoria si la tabla tiene una columna específica.
     */
    private static function tieneColumnaAsistencia(string $columna): bool
    {
        if (self::$columnasAsistenciaCache === null) {
            $columnas = Schema::getColumnListing('registros_asistencia');
            self::$columnasAsistenciaCache = array_fill_keys($columnas, true);
        }

        return isset(self::$columnasAsistenciaCache[$columna]);
    }

    /**
     * Permite reiniciar el cache de columnas durante pruebas o migraciones.
     */
    public static function flushColumnasCache(): void
    {
        self::$columnasAsistenciaCache = null;
    }

    /**
     * Procesa la lectura del código QR o código de barras
     */
    public function procesarQr(Request $request)
    {
        Gate::authorize('asistencia.escanear');

        $validated = $request->validate([
            'evento_id' => [
                'bail', 'nullable',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_int($value) && ! is_string($value)) {
                        $fail('El identificador del evento no es válido.');
                    }
                },
                'integer', 'min:1', 'max:'.PHP_INT_MAX, 'exists:eventos,id',
            ],
        ]);
        $eventoId = isset($validated['evento_id']) ? (int) $validated['evento_id'] : null;
        if ($eventoId !== null && ! self::tieneColumnaAsistencia('evento_id')) {
            throw ValidationException::withMessages(['evento_id' => 'El registro por evento no está disponible en este esquema.']);
        }

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

            // Restricción estricta contra registros duplicados en el mismo evento
            if ($eventoId !== null) {
                $yaAsistioEvento = $personaModel->asistencias()
                    ->where('evento_id', $eventoId)
                    ->exists();

                if ($yaAsistioEvento) {
                    return response()->json([
                        'status' => 'warning',
                        'msg' => 'Asistencia ya registrada previamente para este evento escolar.',
                        'persona' => $nombreCompleto,
                    ]);
                }
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

            if (self::tieneColumnaAsistencia('modo_registro')) {
                $asistenciaData['modo_registro'] = 'QR';
            }
            if (self::tieneColumnaAsistencia('id_persona')) {
                $asistenciaData['id_persona'] = (string) $idPersona;
            }
            if (self::tieneColumnaAsistencia('rol')) {
                $asistenciaData['rol'] = $rol;
            }
            if ($eventoId !== null) {
                $asistenciaData['evento_id'] = $eventoId;
            }

            DB::transaction(function () use ($personaModel, $asistenciaData, $rol, $eventoId): void {
                if ($eventoId !== null && Evento::query()->whereKey($eventoId)->lockForUpdate()->first(['id']) === null) {
                    throw ValidationException::withMessages(['evento_id' => 'El evento seleccionado ya no está disponible.']);
                }

                $personaModel->asistencias()->create($asistenciaData);

                // Actualizar convocatoria explícita si la tabla existe
                if ($rol === 'Tutor' && isset($asistenciaData['evento_id']) && Schema::hasTable('evento_convocatorias')) {
                    $affected = DB::table('evento_convocatorias')
                        ->where('evento_id', $asistenciaData['evento_id'])
                        ->where('tutor_id', $personaModel->id)
                        ->update([
                            'asistio' => true,
                            'fecha_asistencia' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);

                    if ($affected === 0) {
                        // Tutor asistente no convocado previamente (walk-in): registrar para cálculo exacto
                        DB::table('evento_convocatorias')->insertOrIgnore([
                            'evento_id' => $asistenciaData['evento_id'],
                            'tutor_id' => $personaModel->id,
                            'alumno_matricula' => $personaModel->matricula_alumno,
                            'notificado' => false,
                            'asistio' => true,
                            'fecha_asistencia' => Carbon::now(),
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }
            });

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

                $msgResponse = 'Asistencia registrada (notificación en cola)';
            } elseif ($rol === 'Tutor') {
                $nombresAlumnos = $personaModel->nombres_alumnos;
                $msgResponse = ! empty($nombresAlumnos)
                    ? "Asistencia registrada correctamente. Familia de: {$nombresAlumnos}."
                    : 'Asistencia de tutor registrada correctamente.';
            } else {
                $msgResponse = 'Asistencia registrada';
            }
            // ==========================================

            $responsePayload = [
                'status' => 'success',
                'msg' => $msgResponse,
                'persona' => $nombreCompleto,
                'hora' => Carbon::now()->format('h:i A'),
            ];

            if ($rol === 'Tutor' && ! empty($personaModel->nombres_alumnos)) {
                $responsePayload['alumnos_vinculados'] = $personaModel->nombres_alumnos;
            }

            return response()->json($responsePayload);

        } catch (ValidationException $exception) {
            throw $exception;
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
                'destination' => $user->isOperador() ? 'logout' : 'dashboard',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'msg' => 'Contraseña incorrecta',
        ]);
    }
}
