<?php

namespace App\Console\Commands;

use App\Models\Personal;
use App\Models\Tutor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncTelegramUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los mensajes entrantes de Telegram para vincular cuentas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = config('telegram.bot_token');
        if (empty($token)) {
            $this->error('No hay token de Telegram configurado.');

            return Command::FAILURE;
        }

        $offset = Cache::get('telegram_bot_offset', 0);
        $vinculadosNuevos = [];

        try {
            $response = Http::timeout(5)
                ->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 1,
                ]);

            if ($response->successful()) {
                $updates = $response->json('result');

                foreach ($updates as $update) {
                    $offset = max($offset, $update['update_id'] + 1);

                    if (isset($update['message']['text'])) {
                        $text = $update['message']['text'];
                        $chatId = $update['message']['chat']['id'];

                        if (preg_match('/^\/start tutor_(\d+)$/', $text) || preg_match('/^\/start personal_(\d+)$/', $text)) {
                            // 3.1 MEDIDA INMEDIATA: Bloquear vinculaciones por identificadores secuenciales directos (OWASP A01)
                            Log::warning('[Telegram Security] Intento de vinculación bloqueado por identificador secuencial inseguro.', [
                                'chat_id' => $chatId,
                            ]);

                            $mensajeAviso = "⚠️ *Aviso de Seguridad Institucional — CAM #48*\n\n"
                                          ."El método de vinculación mediante identificador numérico directo ha sido deshabilitado permanentemente para proteger la privacidad de los alumnos y del personal escolar.\n\n"
                                          .'Por favor solicite en el plantel una invitación institucional verificada.';

                            Http::timeout(5)->connectTimeout(3)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                                'chat_id' => $chatId,
                                'text' => $mensajeAviso,
                                'parse_mode' => 'Markdown',
                            ]);
                        } elseif (preg_match('/^\/start VINC_([A-Za-z0-9_]{48})$/', $text, $matches)) {
                            // 3.2 Protocolo Criptográfico Seguro de Vinculación (docs/seguridad/protocolo_vinculacion_telegram.md)
                            $rawToken = $matches[1];
                            $tokenHash = hash('sha256', $rawToken);

                            // Verificar si la tabla telegram_vinculaciones existe en el entorno actual
                            if (Schema::hasTable('telegram_vinculaciones')) {
                                DB::transaction(function () use ($tokenHash, $chatId, $token, &$vinculadosNuevos) {
                                    $vinculacion = DB::table('telegram_vinculaciones')
                                        ->where('token_hash', $tokenHash)
                                        ->whereNull('used_at')
                                        ->where('expires_at', '>', now())
                                        ->lockForUpdate()
                                        ->first();

                                    if (! $vinculacion) {
                                        Http::timeout(5)->connectTimeout(3)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                                            'chat_id' => $chatId,
                                            'text' => '⚠️ El enlace de vinculación es inválido, ya fue utilizado o ha expirado (vigencia de 30 minutos). Solicite una nueva invitación en el plantel.',
                                        ]);

                                        return;
                                    }

                                    // Asociación según tipo polimórfico
                                    if ($vinculacion->vinculable_type === Tutor::class) {
                                        $tutor = Tutor::find($vinculacion->vinculable_id);
                                        if ($tutor) {
                                            $tutor->t1_telegram_chat_id = (string) $chatId;
                                            $tutor->save();
                                            $vinculadosNuevos[] = 'Tutor: '.$tutor->t1_nombre;
                                        }
                                    } elseif ($vinculacion->vinculable_type === Personal::class) {
                                        $personal = Personal::find($vinculacion->vinculable_id);
                                        if ($personal) {
                                            $personal->telegram_chat_id = (string) $chatId;
                                            $personal->save();
                                            $vinculadosNuevos[] = 'Personal: '.$personal->nombre_completo;
                                        }
                                    }

                                    // Consumo atómico único (Single-use)
                                    DB::table('telegram_vinculaciones')
                                        ->where('id', $vinculacion->id)
                                        ->update([
                                            'used_at' => now(),
                                            'chat_id_registrado' => (string) $chatId,
                                        ]);

                                    Http::timeout(5)->connectTimeout(3)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                                        'chat_id' => $chatId,
                                        'text' => "🎉 *¡Vinculación Criptográfica Exitosa!*\n\nSu cuenta de Telegram ha sido verificada y vinculada exitosamente con el sistema institucional FamilyLock CAM #48.",
                                        'parse_mode' => 'Markdown',
                                    ]);
                                });
                            } else {
                                Log::info('[Telegram Security] Token VINC recibido pero la tabla telegram_vinculaciones aún no está instalada en el esquema institucional.');

                                Http::timeout(5)->connectTimeout(3)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                                    'chat_id' => $chatId,
                                    'text' => '⚙️ El sistema de vinculación criptográfica se encuentra en preparación para la versión 1.4.6. Próximamente estará disponible.',
                                ]);
                            }
                        }
                    }
                }

                // Actualizamos el offset para no volver a leer los mismos mensajes
                Cache::put('telegram_bot_offset', $offset, now()->addDays(1));
            }
        } catch (\Exception $e) {
            Log::error('Error en SyncTelegramUpdates: '.$e->getMessage());
            $this->error('Error de sincronización: '.$e->getMessage());

            return Command::FAILURE;
        }

        if (count($vinculadosNuevos) > 0) {
            $this->info('Vinculaciones nuevas: '.implode(', ', $vinculadosNuevos));
        } else {
            $this->info('Sincronización completada. Ningún usuario nuevo vinculado.');
        }

        return Command::SUCCESS;
    }
}
