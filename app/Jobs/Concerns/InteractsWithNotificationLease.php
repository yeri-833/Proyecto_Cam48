<?php

namespace App\Jobs\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait InteractsWithNotificationLease
{
    public const string ESTADO_PENDIENTE = 'PENDIENTE';

    public const string ESTADO_EN_PROCESO = 'EN_PROCESO';

    public const string ESTADO_ACEPTADO_PROVEEDOR = 'ACEPTADO_PROVEEDOR';

    public const string ESTADO_PARCIAL = 'PARCIAL';

    public const string ESTADO_FALLIDO = 'FALLIDO';

    public const string ESTADO_SIN_CANAL = 'SIN_CANAL_DISPONIBLE';

    /**
     * Estados terminales que no deben volver a procesarse por idempotencia.
     * Incluye 'ENTREGADO' por retrocompatibilidad con registros históricos.
     *
     * @var array<int, string>
     */
    protected static array $estadosCompletados = [
        self::ESTADO_ACEPTADO_PROVEEDOR,
        self::ESTADO_PARCIAL,
        self::ESTADO_SIN_CANAL,
        'ENTREGADO',
    ];

    protected ?string $activeOwnerToken = null;

    protected ?string $activeIdempotencyKey = null;

    /**
     * Adquiere de forma atómica el lease de ejecución para una clave de idempotencia.
     *
     * @return string 'ACQUIRED' | 'ALREADY_COMPLETED' | 'LOCKED_BY_OTHER' | 'NO_TABLE'
     */
    protected function acquireNotificationLease(
        string $idempotencyKey,
        string $destinatarioTipo,
        string $destinatarioId,
        string $canal,
        int $leaseSeconds = 300
    ): string {
        if (! Schema::hasTable('notificaciones_procesadas')) {
            return 'NO_TABLE';
        }

        $this->activeIdempotencyKey = $idempotencyKey;
        $this->activeOwnerToken = bin2hex(random_bytes(16));

        $now = Carbon::now();
        $leaseExpires = $now->copy()->addSeconds($leaseSeconds);
        $hasOwnerCol = Schema::hasColumn('notificaciones_procesadas', 'owner_token');

        $insertData = [
            'idempotency_key' => $idempotencyKey,
            'destinatario_tipo' => $destinatarioTipo,
            'destinatario_id' => $destinatarioId,
            'canal' => $canal,
            'estado' => self::ESTADO_EN_PROCESO,
            'intentos' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            $insertData['owner_token'] = $this->activeOwnerToken;
            $insertData['lease_expires_at'] = $leaseExpires;
        }

        // 1. Intento atómico de inserción inicial protegido por restricción UNIQUE
        $inserted = DB::table('notificaciones_procesadas')->insertOrIgnore($insertData);

        if ($inserted === 1) {
            return 'ACQUIRED';
        }

        // 2. El registro ya existe: intentar reclamación atómica condicional en una sola sentencia UPDATE
        $claimQuery = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->where(function ($query) use ($hasOwnerCol, $now): void {
                $query->whereIn('estado', [self::ESTADO_PENDIENTE, self::ESTADO_FALLIDO])
                    ->orWhere(function ($sub) use ($hasOwnerCol, $now): void {
                        $sub->where('estado', self::ESTADO_EN_PROCESO);
                        if ($hasOwnerCol) {
                            $sub->where(function ($leaseQuery) use ($now): void {
                                $leaseQuery->whereNull('lease_expires_at')
                                    ->orWhere('lease_expires_at', '<', $now);
                            });
                        } else {
                            $sub->where('updated_at', '<', $now->copy()->subSeconds(300));
                        }
                    });
            });

        $claimData = [
            'estado' => self::ESTADO_EN_PROCESO,
            'intentos' => DB::raw('intentos + 1'),
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            $claimData['owner_token'] = $this->activeOwnerToken;
            $claimData['lease_expires_at'] = $leaseExpires;
        }

        $acquired = $claimQuery->update($claimData);

        if ($acquired === 1) {
            return 'ACQUIRED';
        }

        // 3. No se pudo reclamar: inspeccionar estado actual únicamente para diagnosticar el motivo
        $actual = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first(['estado']);

        if ($actual && in_array($actual->estado, self::$estadosCompletados, true)) {
            $this->activeOwnerToken = null;

            return 'ALREADY_COMPLETED';
        }

        $this->activeOwnerToken = null;

        return 'LOCKED_BY_OTHER';
    }

    /**
     * Finaliza el lease actual solo si este worker sigue siendo el propietario legítimo en EN_PROCESO.
     *
     * @param  array<string, string>  $detalleCanales
     */
    protected function completeNotificationLease(
        string $estadoFinal,
        array $detalleCanales = [],
        ?string $ultimoError = null,
        bool $huboAceptacion = true
    ): bool {
        if (! $this->activeIdempotencyKey || ! Schema::hasTable('notificaciones_procesadas')) {
            return false;
        }

        $now = Carbon::now();
        $hasOwnerCol = Schema::hasColumn('notificaciones_procesadas', 'owner_token');
        $hasDetalleCol = Schema::hasColumn('notificaciones_procesadas', 'detalle_canales');

        $query = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $this->activeIdempotencyKey)
            ->where('estado', self::ESTADO_EN_PROCESO);

        if ($hasOwnerCol && $this->activeOwnerToken !== null) {
            $query->where('owner_token', $this->activeOwnerToken);
        }

        $updateData = [
            'estado' => $estadoFinal,
            'fecha_entrega' => $huboAceptacion ? $now : null,
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            $updateData['lease_expires_at'] = null;
        }

        if ($hasDetalleCol && ! empty($detalleCanales)) {
            $updateData['detalle_canales'] = json_encode($detalleCanales, JSON_UNESCAPED_UNICODE);
        }

        if ($ultimoError !== null) {
            $updateData['ultimo_error'] = substr($ultimoError, 0, 500);
        }

        $updated = $query->update($updateData);

        if ($updated === 0) {
            Log::warning('[NotificationLease] El worker perdió la propiedad del lease antes de finalizar.', [
                'idempotency_key' => $this->activeIdempotencyKey,
                'owner_token' => $this->activeOwnerToken,
                'estado_intentado' => $estadoFinal,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Marca el intento como fallido solo si este worker sigue siendo el propietario del lease en EN_PROCESO.
     *
     * @param  array<string, string>  $detalleCanales
     */
    protected function failNotificationLease(string $errorMessage, array $detalleCanales = []): bool
    {
        if (! $this->activeIdempotencyKey || ! Schema::hasTable('notificaciones_procesadas')) {
            return false;
        }

        $now = Carbon::now();
        $hasOwnerCol = Schema::hasColumn('notificaciones_procesadas', 'owner_token');
        $hasDetalleCol = Schema::hasColumn('notificaciones_procesadas', 'detalle_canales');

        $query = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $this->activeIdempotencyKey)
            ->where('estado', self::ESTADO_EN_PROCESO);

        if ($hasOwnerCol && $this->activeOwnerToken !== null) {
            $query->where('owner_token', $this->activeOwnerToken);
        }

        $updateData = [
            'estado' => self::ESTADO_FALLIDO,
            'ultimo_error' => substr($errorMessage, 0, 500),
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            $updateData['lease_expires_at'] = null;
        }

        if ($hasDetalleCol && ! empty($detalleCanales)) {
            $updateData['detalle_canales'] = json_encode($detalleCanales, JSON_UNESCAPED_UNICODE);
        }

        return $query->update($updateData) === 1;
    }

    /**
     * Manejo seguro de fallo terminal en `failed()` sin sobreescribir leases de otros workers ni estados completados.
     */
    protected function handleTerminalFailure(string $idempotencyKey, ?\Throwable $exception = null, int $tries = 3): void
    {
        if (! Schema::hasTable('notificaciones_procesadas')) {
            return;
        }

        $now = Carbon::now();
        $hasOwnerCol = Schema::hasColumn('notificaciones_procesadas', 'owner_token');
        $mensajeError = 'Agotados los '.$tries.' intentos: '.($exception ? substr($exception->getMessage(), 0, 400) : 'Error desconocido');

        $query = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->whereNotIn('estado', self::$estadosCompletados);

        if ($hasOwnerCol) {
            if ($this->activeOwnerToken !== null) {
                $query->where('owner_token', $this->activeOwnerToken);
            } else {
                // Si failed() se invoca en una instancia sin token (ej. timeout externo),
                // solo marcar FALLIDO si no hay un lease activo vigente de otro worker.
                $query->where(function ($sub) use ($now): void {
                    $sub->where('estado', self::ESTADO_FALLIDO)
                        ->orWhere(function ($expired) use ($now): void {
                            $expired->where('estado', self::ESTADO_EN_PROCESO)
                                ->where(function ($sq) use ($now): void {
                                    $sq->whereNull('lease_expires_at')
                                        ->orWhere('lease_expires_at', '<', $now);
                                });
                        });
                });
            }
        }

        $updateData = [
            'estado' => self::ESTADO_FALLIDO,
            'ultimo_error' => $mensajeError,
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            $updateData['lease_expires_at'] = null;
        }

        $query->update($updateData);
    }
}
