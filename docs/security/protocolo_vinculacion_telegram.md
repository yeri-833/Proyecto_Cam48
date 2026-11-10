# Protocolo Criptográfico de Vinculación Segura de Telegram — FamilyLock CAM #48

## 1. Modelo de Amenazas y Vulnerabilidades Identificadas

### Vulnerabilidad Crítica en Código Actual
En el código actual (`TutorController.php` y `PersonalController.php`), las invitaciones a Telegram se generan con identificadores secuenciales directos e inseguros:
- `https://t.me/{bot}?start=tutor_{id}`
- `https://t.me/{bot}?start=personal_{id}`

### Riesgos OWASP Asociados
- **IDOR / Enumeración de Identificadores (OWASP A01: Broken Access Control):** Cualquier usuario malicioso puede enviar `/start tutor_1`, `/start tutor_2`... al bot y adueñarse de las alertas en tiempo real de cualquier alumno de la escuela.
- **Fuga Masiva de Datos de Menores (OWASP A03: Sensitive Data Exposure):** El atacante recibiría la asistencia, nombre completo y hora de entrada/salida de los niños.
- **Tokens Sin Expiración:** Los enlaces no vencen nunca.

---

## 2. Restricción Técnica de la API de Telegram (Parámetro Deep-Linking `start`)

De acuerdo con la documentación oficial de la [Telegram Bot API - Deep Linking](https://core.telegram.org/bots/features#deep-linking):
> *"The parameter can comprise up to **64 characters** from the set `[A-Za-z0-9_-]`."*

### Diagnóstico del Diseño Anterior
El diseño anterior proponía:
- Prefijo: `VINC_` (5 caracteres)
- Token hex: 64 caracteres (`bin2hex(random_bytes(32))`)
- **Total: 69 caracteres ❌ (EXCEDE el límite de Telegram por 5 caracteres)**.
Esto provocaría que la aplicación de Telegram trunque el parámetro o rechace la solicitud deep link en clientes iOS/Android/Web.

---

## 3. Rediseño Criptográfico Ajustado al Límite de 64 Caracteres

### Especificación del Token
Se rediseña el formato para cumplir estrictamente con el límite de 64 caracteres manteniendo una entropía criptográfica de nivel bancario:

1. **Generación del Token Plano (Raw Token):**
   - **Opción A (Hexadecimal - Recomendada por legibilidad y robustez):**
     - Longitud de bytes aleatorios: **24 bytes** criptográficamente seguros (`random_bytes(24)`).
     - Representación hexadecimal: **48 caracteres hex** (`bin2hex(...)`).
     - Entropía: **192 bits** ($2^{192}$ combinaciones, criptográficamente invulnerable a ataques por fuerza bruta).
     - Parámetro Deep Link: `VINC_` + 48 caracteres hex = **53 caracteres totales ✅** (Margen de 11 caracteres bajo el límite de 64).
   - **Opción B (Base64URL - Máxima entropía):**
     - Longitud de bytes: **32 bytes** (256 bits).
     - Codificación: Base64URL sin relleno (`rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=')`) = **43 caracteres**.
     - Parámetro Deep Link: `VINC_` + 43 caracteres = **48 caracteres totales ✅**.

2. **Almacenamiento Criptográfico Seguro (Zero-Plaintext Storage):**
   - El token plano **NUNCA** se almacena en la base de datos.
   - En la tabla se almacena exclusivamente el **hash SHA-256** del token plano:
     ```php
     $tokenHash = hash('sha256', $rawToken); // 64 caracteres hex fijos
     ```
   - Si la base de datos o un respaldo sufre una brecha, los atacantes **no podrán vincularse** a Telegram porque desconocen el token plano.

3. **Vencimiento y Uso Único:**
   - TTL (Time-To-Live): **30 minutos** (`expires_at = now()->addMinutes(30)`).
   - Consumo atómico único: Se marca con `used_at = now()`.

---

## 4. Esquema de Base de Datos Propuesto (Pendiente de Aprobación Institucional)

> *Nota: Esta estructura es un diseño técnico formal. No se ejecuta ninguna migración sin autorización expresa.*

```sql
CREATE TABLE `telegram_vinculaciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token_hash` CHAR(64) NOT NULL UNIQUE COMMENT 'Hash SHA-256 del token plano generado',
    `vinculable_type` VARCHAR(100) NOT NULL COMMENT 'App\\Models\\Tutor o App\\Models\\Personal',
    `vinculable_id` VARCHAR(50) NOT NULL COMMENT 'ID institucional del tutor o empleado',
    `generado_por_usuario_id` INT NULL COMMENT 'ID del usuario administrador o docente emisor',
    `chat_id_registrado` VARCHAR(50) NULL COMMENT 'Chat ID de Telegram enlazado',
    `expires_at` TIMESTAMP NOT NULL COMMENT 'Fecha límite de uso (30 minutos tras generación)',
    `used_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Fecha de consumo del token',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token_lookup` (`token_hash`, `used_at`, `expires_at`),
    INDEX `idx_vinculable` (`vinculable_type`, `vinculable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Algoritmo de Generación y Validación

### Generación (Backend Web):
```php
// 1. Generar 24 bytes aleatorios (48 caracteres hex)
$rawToken = bin2hex(random_bytes(24));
$deepLinkParam = 'VINC_' . $rawToken; // Longitud exacta: 53 caracteres (<= 64)

// 2. Calcular hash SHA-256 para guardar en BD
$tokenHash = hash('sha256', $rawToken);

// 3. Registrar con TTL de 30 minutos
TelegramVinculacion::create([
    'token_hash' => $tokenHash,
    'vinculable_type' => get_class($tutor),
    'vinculable_id' => (string) $tutor->id,
    'generado_por_usuario_id' => auth()->id(),
    'expires_at' => now()->addMinutes(30),
]);

// 4. Enlace que se envía por Correo o WhatsApp:
$enlaceTelegram = "https://t.me/" . config('telegram.bot_username') . "?start=" . $deepLinkParam;
```

### Consumo Atómico (Bot Telegram):
```php
// 1. Verificar formato exacto (VINC_ + 48 caracteres hexadecimales)
if (preg_match('/^\/start VINC_([a-f0-9]{48})$/', $text, $matches)) {
    $rawToken = $matches[1];
    $tokenHash = hash('sha256', $rawToken);

    // 2. Transacción con bloqueo de fila (Race Condition Protection)
    DB::transaction(function () use ($tokenHash, $chatId, &$mensajeRespuesta) {
        $vinculacion = TelegramVinculacion::where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        if (! $vinculacion) {
            $mensajeRespuesta = "⚠️ El enlace de vinculación es inválido, ya fue utilizado o ha expirado. Solicite una nueva invitación en el plantel.";
            return;
        }

        // 3. Asociar al tutor o personal institucional
        $persona = $vinculacion->vinculable;
        if ($persona instanceof \App\Models\Tutor) {
            $persona->t1_telegram_chat_id = $chatId;
            $persona->save();
        } elseif ($persona instanceof \App\Models\Personal) {
            $persona->telegram_chat_id = $chatId;
            $persona->save();
        }

        // 4. Invalidar token inmediatamente
        $vinculacion->used_at = now();
        $vinculacion->chat_id_registrado = $chatId;
        $vinculacion->save();

        $mensajeRespuesta = "🎉 ¡Vinculación completada con éxito! A partir de ahora recibirá las notificaciones de asistencia escolar.";
    });

    $this->responderTelegram($chatId, $mensajeRespuesta);
}
```

---

## 6. Resumen de Cumplimiento de Requisitos

| Requisito | Estado | Solución Técnica Implementada |
|---|---|---|
| **Límite Telegram (<= 64 chars)** | ✅ Cumple | Longitud total: **53 caracteres** (`VINC_` [5] + token [48]). |
| **Aleatoriedad Criptográfica** | ✅ Cumple | `random_bytes(24)` con 192 bits de entropía CSPRNG. |
| **Vencimiento (TTL)** | ✅ Cumple | Expiración estricta de 30 minutos controlada por base de datos. |
| **Uso Único (Single-Use)** | ✅ Cumple | `used_at` obligatorio con condición en consulta. |
| **Almacenamiento Seguro** | ✅ Cumple | Solo se almacena `token_hash` (SHA-256); token plano nunca persiste. |
| **Consumo Atómico** | ✅ Cumple | `DB::transaction()` con `lockForUpdate()` para prevenir race conditions. |
| **Asociación Segura** | ✅ Cumple | Relación polimórfica explícita con el registro del alumno/personal. |
