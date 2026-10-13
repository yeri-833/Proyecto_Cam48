<?php

namespace App\Console\Commands;

use App\Models\Personal;
use App\Models\Tutor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

                        if (preg_match('/^\/start tutor_(\d+)$/', $text, $matches)) {
                            $tutorId = $matches[1];
                            $tutor = Tutor::find($tutorId);

                            if ($tutor && empty($tutor->t1_telegram_chat_id)) {
                                $tutor->t1_telegram_chat_id = $chatId;
                                $tutor->save();

                                $vinculadosNuevos[] = $tutor->t1_nombre;

                                // Enviar bienvenida y enlace al grupo
                                $mensaje = "🎉 *¡Vinculación Exitosa!*\n\n"
                                         ."Hola *{$tutor->t1_nombre}*, tu cuenta ha sido conectada al sistema del CAM #48.\n\n"
                                         ."A partir de este momento, recibirás mensajes directos por aquí.\n\n"
                                         ."📢 *¡Paso Final! Únete al grupo oficial:*\n"
                                         ."Para recibir los avisos y circulares generales de la escuela, por favor únete a nuestro canal tocando el siguiente enlace:\n"
                                         .'👉 https://t.me/cam48comunicados';

                                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                                    'chat_id' => $chatId,
                                    'text' => $mensaje,
                                    'parse_mode' => 'Markdown',
                                ]);
                            }
                        } elseif (preg_match('/^\/start personal_(\d+)$/', $text, $matches)) {
                            $personalId = $matches[1];
                            $personal = Personal::find($personalId);

                            if ($personal && empty($personal->telegram_chat_id)) {
                                $personal->telegram_chat_id = $chatId;
                                $personal->save();

                                $vinculadosNuevos[] = 'Prof. '.$personal->nombre_completo;

                                $mensaje = "🎉 *¡Vinculación Exitosa!*\n\n"
                                         ."Hola *{$personal->nombre_completo}*, tu cuenta ha sido conectada al portal del Personal del CAM #48.\n\n"
                                         ."Recibirás avisos institucionales, notificaciones de juntas y circulares directamente en este chat.\n\n"
                                         .'📢 *Recuerda unirte al grupo del personal si no lo has hecho:* (Consulta el enlace con Dirección).';

                                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                                    'chat_id' => $chatId,
                                    'text' => $mensaje,
                                    'parse_mode' => 'Markdown',
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
