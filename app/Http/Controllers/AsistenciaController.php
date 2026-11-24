<?php

namespace App\Http\Controllers;

use App\Jobs\NotificarAsistenciaJob;
use App\Models\Alumno;
use App\Models\Evento;
use App\Models\Personal;
use App\Models\RegistroAsistencia;
use App\Models\Tutor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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

            // Registrar la asistencia polimórfica (y conservar campos heredados condicionalmente para compatibilidad)
            $asistenciaData = [];

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

            $resultadoRegistro = DB::transaction(function () use ($personaModel, $asistenciaData, $eventoId, $nombreCompleto): Carbon|JsonResponse {
                // La fila de la persona existe aun cuando todavía no hay asistencias que bloquear.
                $personaBloqueada = $personaModel->newQuery()->whereKey($personaModel->getKey())->lockForUpdate()->first();

                if ($personaBloqueada === null) {
                    return response()->json(['status' => 'error', 'msg' => 'El código no existe en el sistema']);
                }

                if (($personaBloqueada instanceof Alumno || $personaBloqueada instanceof Personal) && ! $personaBloqueada->activo) {
                    return response()->json(['status' => 'error', 'msg' => 'La persona está inactiva o de baja']);
                }

                if ($eventoId !== null && Evento::query()->whereKey($eventoId)->sharedLock()->first(['id']) === null) {
                    throw ValidationException::withMessages(['evento_id' => 'El evento seleccionado ya no está disponible.']);
                }

                if ($eventoId !== null && $personaBloqueada->asistencias()->where('evento_id', $eventoId)->lockForUpdate()->first(['id']) !== null) {
                    return response()->json([
                        'status' => 'warning',
                        'msg' => 'Asistencia ya registrada previamente para este evento escolar.',
                        'persona' => $nombreCompleto,
                    ]);
                }

                $momentoRegistro = Carbon::now();
                $fechaHoy = $momentoRegistro->toDateString();
                /** @var RegistroAsistencia|null $ultimoRegistro */
                $ultimoRegistro = $personaBloqueada->asistencias()
                    ->whereDate('fecha', $fechaHoy)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first(['hora']);

                if ($ultimoRegistro) {
                    try {
                        $ultimoTiempo = Carbon::parse($fechaHoy.' '.$ultimoRegistro->hora);
                        if (abs($momentoRegistro->diffInSeconds($ultimoTiempo)) < 300) {
                            return response()->json([
                                'status' => 'warning',
                                'msg' => 'Pase registrado recientemente.',
                                'persona' => $nombreCompleto,
                            ]);
                        }
                    } catch (\Exception) {
                        Log::warning('asistencia_cooldown_hora_invalida');
                    }
                }

                $personaBloqueada->asistencias()->create([
                    ...$asistenciaData,
                    'fecha' => $fechaHoy,
                    'hora' => $momentoRegistro->toTimeString(),
                ]);

                // Actualizar convocatoria explícita si la tabla existe
                if ($personaBloqueada instanceof Tutor && $eventoId !== null && Schema::hasTable('evento_convocatorias')) {
                    $convocatoria = DB::table('evento_convocatorias')
                        ->where('evento_id', $eventoId)
                        ->where('tutor_id', $personaBloqueada->id);
                    $datosAsistencia = [
                        'asistio' => true,
                        'fecha_asistencia' => $momentoRegistro,
                        'updated_at' => $momentoRegistro,
                    ];

                    if ((clone $convocatoria)->lockForUpdate()->first(['id']) !== null) {
                        $convocatoria->update($datosAsistencia);
                    } else {
                        DB::table('evento_convocatorias')->insert([
                            'evento_id' => $eventoId,
                            'tutor_id' => $personaBloqueada->id,
                            'alumno_matricula' => $personaBloqueada->matricula_alumno,
                            'notificado' => false,
                            'created_at' => $momentoRegistro,
                            ...$datosAsistencia,
                        ]);
                    }
                }

                return $momentoRegistro;
            }, 3);
            if ($resultadoRegistro instanceof JsonResponse) {
                return $resultadoRegistro;
            }

            // =========== ALERTAS AUTOMÁTICAS (WHATSAPP + TELEGRAM) ===========
            $horaFormateada = $resultadoRegistro->format('h:i A');
            if ($rol === 'Alumno' && isset($alumno) && $alumno->tutor) {
                $tipoPase = $resultadoRegistro->hour < 13 ? 'ENTRADA' : 'SALIDA';

                NotificarAsistenciaJob::dispatch(
                    $alumno,
                    $tipoPase,
                    $horaFormateada,
                    $nombreCompleto,
                    $resultadoRegistro->toDateString()
                )->afterCommit();

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
                'hora' => $horaFormateada,
            ];

            if ($rol === 'Tutor' && ! empty($personaModel->nombres_alumnos)) {
                $responsePayload['alumnos_vinculados'] = $personaModel->nombres_alumnos;
            }

            return response()->json($responsePayload);

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            Log::error('asistencia_qr_registro_fallido', ['tipo' => $e::class]);

            return response()->json([
                'status' => 'error',
                'msg' => 'Ocurrió un error inesperado. Intenta de nuevo.',
            ]);
        }
    }

    /**
     * Valida la contraseña del usuario para salir del modo Kiosco
     */
    public function desbloquearKiosco(Request $request): JsonResponse
    {
        Gate::authorize('asistencia.escanear');

        /** @var User $user */
        $user = $request->user();
        $limites = [
            'kiosco:cuenta:'.$user->getAuthIdentifier() => 5,
            'kiosco:origen:'.hash('sha256', (string) $request->ip()) => 20,
        ];
        foreach ($limites as $clave => $maximo) {
            if (RateLimiter::tooManyAttempts($clave, $maximo)) {
                return $this->desbloqueoLimitado($clave);
            }
        }
        foreach ($limites as $clave => $maximo) {
            $intentos = RateLimiter::increment($clave, 60);
            if ($intentos < 1 || $intentos > $maximo) {
                return $this->desbloqueoLimitado($clave);
            }
        }

        $password = $request->input('password');

        if (! is_string($password) || $password === '') {
            return response()->json([
                'status' => 'error',
                'msg' => 'Contraseña vacía',
            ]);
        }

        if (Hash::check($password, $user->password)) {
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

    private function desbloqueoLimitado(string $clave): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'msg' => 'Demasiados intentos. Intente nuevamente más tarde.',
        ], 429, ['Retry-After' => (string) max(1, RateLimiter::availableIn($clave))]);
    }
}
