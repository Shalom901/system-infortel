<?php
namespace App\Helpers;

class SecurityLogger
{
    /**
     * Dispara una alerta en tiempo real al dispositivo del administrador
     */
    public static function alertarAtaque(string $tipoAtaque, string $email, string $ip): void
    {
        // Configuración de tu Bot de Telegram (Obtenido en BotFather)
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'];
        $chatId   = $_ENV['TELEGRAM_CHAT_ID'];

        $fecha = date('Y-m-d H:i:s');
        $mensaje = "🚨 *ALERTA DE SEGURIDAD MRX* 🚨\n\n"
                 . "⚠️ *Tipo:* {$tipoAtaque}\n"
                 . "👤 *Objetivo:* {$email}\n"
                 . "🌐 *IP Origen:* {$ip}\n"
                 . "🕒 *Hora:* {$fecha}\n\n"
                 . "🛡️ _El sistema ha bloqueado la IP automáticamente._";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        // Llamada cURL asíncrona/rápida para no bloquear el login del usuario real
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $chatId,
            'text' => $mensaje,
            'parse_mode' => 'Markdown'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Timeout corto para evitar latencia
        curl_exec($ch);
        curl_close($ch);
    }
}