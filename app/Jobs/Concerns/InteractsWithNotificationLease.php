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

    public const string ESTADO_INCIERTO = 'INCIERTO';

    public const string CANAL_PENDIENTE = 'PENDIENTE';

    public const string CANAL_ACEPTADO = 'ACEPTADO_PROVEEDOR';

    public const string CANAL_FALLIDO = 'FALLIDO';

    public const string CANAL_INCIERTO = 'INCIERTO';

    public const string CANAL_NO_CONFIGURADO = 'NO_CONFIGURADO';

    /**
     * Estados terminales definitivos que no deben volver a procesarse.
     * PARCIAL y SIN_CANAL_DISPONIBLE quedan EXCLUIDOS para permitir
     * la recuperación y reintento de canales fallidos o reconfigurados.
     *
     * @var array<int, string>
     */
    protected static array $estadosCompletados = [
        self::ESTADO_ACEPTADO_PROVEEDOR,
        'ENTREGADO', // retrocompatibilidad histórica
    ];

    protected ?string $activeOwnerToken = null;

    protected ?string $activeIdempotencyKey = null;

    /**
     * Detalle estructurado de canales de la notificación activa.
     *
     * @var array<string, array{estado: string, intentos: int, ultimo_error: ?string, fecha_aceptacion: ?string}>
     */
    protected array $canalesPersistidos = [];

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
            $this->canalesPersistidos = [];

            return 'ACQUIRED';
        }

        // 2. El registro ya existe: intentar reclamación atómica condicional.
        // Se permite reclamar estados recuperables: PENDIENTE, FALLIDO, PARCIAL y SIN_CANAL_DISPONIBLE,
        // así como EN_PROCESO con lease expirado.
        $reclamables = [
            self::ESTADO_PENDIENTE,
            self::ESTADO_FALLIDO,
            self::ESTADO_PARCIAL,
            self::ESTADO_SIN_CANAL,
        ];

        $claimQuery = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->where(function ($query) use ($reclamables, $hasOwnerCol, $now): void {
                $query->whereIn('estado', $reclamables)
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
            $this->cargarCanalesPersistidos($idempotencyKey);

            return 'ACQUIRED';
        }

        // 3. No se pudo reclamar: verificar si ya completó definitivamente
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
     * Carga y normaliza el detalle de canales almacenado previamente en base de datos.
     *
     * @return array<string, array{estado: string, intentos: int, ultimo_error: ?string, fecha_aceptacion: ?string}>
     */
    protected function cargarCanalesPersistidos(?string $idempotencyKey = null): array
    {
        $key = $idempotencyKey ?? $this->activeIdempotencyKey;
        if (! $key || ! Schema::hasTable('notificaciones_procesadas') || ! Schema::hasColumn('notificaciones_procesadas', 'detalle_canales')) {
            $this->canalesPersistidos = [];

            return [];
        }

        $raw = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $key)
            ->value('detalle_canales');

        $this->canalesPersistidos = $this->parsearDetalleCanales($raw);

        return $this->canalesPersistidos;
    }

    /**
     * Parsea y normaliza el JSON de detalle_canales a estructura uniforme por canal.
     *
     * @return array<string, array{estado: string, intentos: int, ultimo_error: ?string, fecha_aceptacion: ?string}>
     */
    protected function parsearDetalleCanales(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $normalizado = [];
        foreach ($decoded as $canal => $info) {
            $canalKey = strtolower(trim((string) $canal));
            if (is_string($info)) {
                $isAceptado = in_array($info, [self::CANAL_ACEPTADO, 'ENTREGADO', 'ENVIADO'], true);
                $normalizado[$canalKey] = [
                    'estado' => $isAceptado ? self::CANAL_ACEPTADO : $info,
                    'intentos' => 1,
                    'ultimo_error' => null,
                    'fecha_aceptacion' => $isAceptado ? Carbon::now()->toIso8601String() : null,
                ];
            } elseif (is_array($info)) {
                $normalizado[$canalKey] = [
                    'estado' => (string) ($info['estado'] ?? self::CANAL_PENDIENTE),
                    'intentos' => (int) ($info['intentos'] ?? 1),
                    'ultimo_error' => isset($info['ultimo_error']) ? (string) $info['ultimo_error'] : null,
                    'fecha_aceptacion' => isset($info['fecha_aceptacion']) ? (string) $info['fecha_aceptacion'] : null,
                ];
            }
        }

        return $normalizado;
    }

    /**
     * Determina si un canal específico debe ser procesado/enviado.
     * Retorna false si el canal ya fue aceptado por el proveedor o está en incertidumbre (timeout).
     */
    protected function debeProcesarCanal(string $canal): bool
    {
        $canalKey = strtolower(trim($canal));

        if (! isset($this->canalesPersistidos[$canalKey])) {
            return true;
        }

        $estado = $this->canalesPersistidos[$canalKey]['estado'];

        if ($this->isCanalAceptado($canalKey)) {
            return false;
        }

        if ($this->isCanalIncierto($canalKey)) {
            return false;
        }

        if ($estado === self::CANAL_NO_CONFIGURADO) {
            return true; // Si ahora está configurado, permitir procesar
        }

        return true;
    }

    /**
     * Comprueba si el canal ya fue aceptado o entregado previamente.
     */
    protected function isCanalAceptado(string $canal): bool
    {
        $canalKey = strtolower(trim($canal));
        $estado = $this->canalesPersistidos[$canalKey]['estado'] ?? null;

        return in_array($estado, [self::CANAL_ACEPTADO, 'ENTREGADO', 'ENVIADO'], true);
    }

    /**
     * Comprueba si el canal se encuentra en estado de resultado incierto (timeout).
     */
    protected function isCanalIncierto(string $canal): bool
    {
        $canalKey = strtolower(trim($canal));
        $estado = $this->canalesPersistidos[$canalKey]['estado'] ?? null;

        return $estado === self::CANAL_INCIERTO;
    }

    /**
     * Retorna el estado actual del canal o null si no existe.
     */
    protected function getEstadoCanal(string $canal): ?string
    {
        $canalKey = strtolower(trim($canal));

        return $this->canalesPersistidos[$canalKey]['estado'] ?? null;
    }

    /**
     * Registra o actualiza el resultado de un canal preservando inmutabilidad en entregas exitosas.
     */
    protected function registrarResultadoCanal(string $canal, string $estado, ?string $error = null): void
    {
        $canalKey = strtolower(trim($canal));
        $previo = $this->canalesPersistidos[$canalKey] ?? null;

        // INMUTABILIDAD: Si el canal ya fue aceptado previamente por el proveedor, nunca degradar a fallido
        if ($previo !== null && in_array($previo['estado'], [self::CANAL_ACEPTADO, 'ENTREGADO', 'ENVIADO'], true)) {
            return;
        }

        $intentos = ($previo['intentos'] ?? 0) + 1;
        $isAceptado = ($estado === self::CANAL_ACEPTADO);
        $fechaAceptacion = $isAceptado
            ? ($previo['fecha_aceptacion'] ?? Carbon::now()->toIso8601String())
            : null;

        $this->canalesPersistidos[$canalKey] = [
            'estado' => $estado,
            'intentos' => $intentos,
            'ultimo_error' => $error ? substr($error, 0, 500) : null,
            'fecha_aceptacion' => $fechaAceptacion,
        ];
    }

    /**
     * Registra en memoria y persiste de inmediato en base de datos el resultado de un canal individual.
     * Garantiza que si el proceso sufre una interrupción o corte intempestivo entre canales,
     * la aceptación del proveedor ya esté registrada y no se vuelva a enviar en el siguiente intento.
     */
    protected function registrarYPersistirCanal(string $canal, string $estado, ?string $error = null): void
    {
        $this->registrarResultadoCanal($canal, $estado, $error);

        if (! $this->activeIdempotencyKey || ! Schema::hasTable('notificaciones_procesadas') || ! Schema::hasColumn('notificaciones_procesadas', 'detalle_canales')) {
            return;
        }

        $query = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $this->activeIdempotencyKey)
            ->where('estado', self::ESTADO_EN_PROCESO);

        if (Schema::hasColumn('notificaciones_procesadas', 'owner_token') && $this->activeOwnerToken !== null) {
            $query->where('owner_token', $this->activeOwnerToken);
        }

        $query->update([
            'detalle_canales' => json_encode($this->canalesPersistidos, JSON_UNESCAPED_UNICODE),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Evalúa si una excepción o mensaje corresponde a un resultado incierto (timeout de red).
     */
    protected function esErrorIncierto(?string $error): bool
    {
        if (empty($error)) {
            return false;
        }

        $errorLower = strtolower($error);
        $patronesInciertos = [
            'timeout',
            'timed out',
            'curl error 28',
            'operation timed out',
            'connection timed out',
            'deadline exceeded',
            'network timeout',
        ];

        foreach ($patronesInciertos as $patron) {
            if (str_contains($errorLower, $patron)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guarda el estado intermedio de la notificación liberando el lease para que
     * el siguiente intento de la cola pueda reclamar y procesar canales pendientes.
     */
    protected function saveInterimLeaseState(string $estado, ?string $ultimoError = null): bool
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
            'estado' => $estado,
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            // Liberar el lease para permitir reintento inmediato por el siguiente worker
            $updateData['lease_expires_at'] = null;
        }

        if ($hasDetalleCol) {
            $updateData['detalle_canales'] = json_encode($this->canalesPersistidos, JSON_UNESCAPED_UNICODE);
        }

        if ($ultimoError !== null) {
            $updateData['ultimo_error'] = substr($ultimoError, 0, 500);
        }

        $updated = $query->update($updateData);

        return $updated === 1;
    }

    /**
     * Finaliza el lease actual solo si este worker sigue siendo el propietario legítimo en EN_PROCESO.
     *
     * @param  array<string, mixed>  $detalleCanales
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

        // Si se suministró detalle de canales adicional, mergear
        if (! empty($detalleCanales)) {
            foreach ($detalleCanales as $canal => $val) {
                if (is_string($val)) {
                    $this->registrarResultadoCanal((string) $canal, $val);
                }
            }
        }

        if ($hasDetalleCol) {
            $updateData['detalle_canales'] = json_encode($this->canalesPersistidos, JSON_UNESCAPED_UNICODE);
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
     * @param  array<string, mixed>  $detalleCanales
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

        // Si se suministró detalle adicional, mergear
        if (! empty($detalleCanales)) {
            foreach ($detalleCanales as $canal => $val) {
                if (is_string($val)) {
                    $this->registrarResultadoCanal((string) $canal, $val, $errorMessage);
                }
            }
        }

        $updateData = [
            'estado' => self::ESTADO_FALLIDO,
            'ultimo_error' => substr($errorMessage, 0, 500),
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            $updateData['lease_expires_at'] = null;
        }

        if ($hasDetalleCol) {
            $updateData['detalle_canales'] = json_encode($this->canalesPersistidos, JSON_UNESCAPED_UNICODE);
        }

        return $query->update($updateData) === 1;
    }

    /**
     * Manejo seguro de fallo terminal en `failed()` preservando canales aceptados y evitando sobreescrituras indebidas.
     */
    protected function handleTerminalFailure(string $idempotencyKey, ?\Throwable $exception = null, int $tries = 3): void
    {
        if (! Schema::hasTable('notificaciones_procesadas')) {
            return;
        }

        $now = Carbon::now();
        $hasOwnerCol = Schema::hasColumn('notificaciones_procesadas', 'owner_token');
        $hasDetalleCol = Schema::hasColumn('notificaciones_procesadas', 'detalle_canales');
        $mensajeError = 'Agotados los '.$tries.' intentos: '.($exception ? substr($exception->getMessage(), 0, 400) : 'Error desconocido');

        // Cargar estado persistido previo para determinar si hubo entregas parciales
        $actual = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $actual || in_array($actual->estado, self::$estadosCompletados, true)) {
            return;
        }

        $canales = $hasDetalleCol ? $this->parsearDetalleCanales($actual->detalle_canales ?? null) : [];
        $huboCanalAceptado = false;
        foreach ($canales as $c) {
            if (in_array($c['estado'], [self::CANAL_ACEPTADO, 'ENTREGADO', 'ENVIADO'], true)) {
                $huboCanalAceptado = true;
                break;
            }
        }

        $estadoTerminal = $huboCanalAceptado ? self::ESTADO_PARCIAL : self::ESTADO_FALLIDO;

        $query = DB::table('notificaciones_procesadas')
            ->where('idempotency_key', $idempotencyKey)
            ->whereNotIn('estado', self::$estadosCompletados);

        if ($hasOwnerCol) {
            if ($this->activeOwnerToken !== null) {
                $query->where('owner_token', $this->activeOwnerToken);
            } else {
                $query->where(function ($sub) use ($now): void {
                    $sub->whereIn('estado', [self::ESTADO_FALLIDO, self::ESTADO_PARCIAL])
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
            'estado' => $estadoTerminal,
            'ultimo_error' => $mensajeError,
            'updated_at' => $now,
        ];

        if ($hasOwnerCol) {
            $updateData['lease_expires_at'] = null;
        }

        $query->update($updateData);
    }
}
