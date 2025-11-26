<?php
class NotificationService {

    /**
     * Enviar notificación por email
     */
    public static function sendEmail($to, $subject, $message) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Price Monitor <noreply@pricemonitor.com>" . "\r\n";

        $htmlMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .price { font-size: 24px; font-weight: bold; color: #4CAF50; }
                .button { background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Alerta de Precio</h1>
                </div>
                <div class='content'>
                    {$message}
                </div>
            </div>
        </body>
        </html>
        ";

        return mail($to, $subject, $htmlMessage, $headers);
    }

    /**
     * Enviar notificación por Telegram
     * Requiere configurar un bot de Telegram y obtener el token
     */
    public static function sendTelegram($chatId, $message) {
        // CONFIGURAR: Obtén tu token de bot en https://t.me/BotFather
        $botToken = 'TU_BOT_TOKEN_AQUI';

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Enviar notificación por WhatsApp
     * Requiere integración con Twilio o similar
     */
    public static function sendWhatsApp($phoneNumber, $message) {
        // CONFIGURAR: Credenciales de Twilio
        $accountSid = 'TU_ACCOUNT_SID';
        $authToken = 'TU_AUTH_TOKEN';
        $twilioNumber = 'whatsapp:+14155238886'; // Número de WhatsApp de Twilio

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $data = [
            'From' => $twilioNumber,
            'To' => 'whatsapp:' . $phoneNumber,
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 201;
    }

    /**
     * Enviar notificación por SMS
     * Requiere integración con Twilio o similar
     */
    public static function sendSMS($phoneNumber, $message) {
        // CONFIGURAR: Credenciales de Twilio
        $accountSid = 'TU_ACCOUNT_SID';
        $authToken = 'TU_AUTH_TOKEN';
        $twilioNumber = '+1234567890'; // Tu número de Twilio

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $data = [
            'From' => $twilioNumber,
            'To' => $phoneNumber,
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 201;
    }

    /**
     * Enviar notificación según el método especificado
     */
    public static function send($method, $contactInfo, $productName, $oldPrice, $newPrice, $url) {
        $message = self::buildMessage($productName, $oldPrice, $newPrice, $url);

        switch ($method) {
            case 'email':
                return self::sendEmail($contactInfo, "¡Bajada de precio en {$productName}!", $message);

            case 'telegram':
                return self::sendTelegram($contactInfo, strip_tags($message));

            case 'whatsapp':
                return self::sendWhatsApp($contactInfo, strip_tags($message));

            case 'sms':
                $shortMessage = "Bajada de precio: {$productName} ahora €{$newPrice} (antes €{$oldPrice})";
                return self::sendSMS($contactInfo, $shortMessage);

            default:
                return false;
        }
    }

    /**
     * Construir mensaje de notificación
     */
    private static function buildMessage($productName, $oldPrice, $newPrice, $url) {
        $discount = $oldPrice - $newPrice;
        $discountPercent = round(($discount / $oldPrice) * 100, 2);

        return "
            <h2>¡Bajada de precio detectada!</h2>
            <p><strong>Producto:</strong> {$productName}</p>
            <p><strong>Precio anterior:</strong> €{$oldPrice}</p>
            <p class='price'>Precio actual: €{$newPrice}</p>
            <p><strong>Ahorro:</strong> €{$discount} ({$discountPercent}%)</p>
            <a href='{$url}' class='button'>Ver producto</a>
        ";
    }
}
?>
