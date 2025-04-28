<?php

namespace App\Services\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppDriver
{
    public function send($to, $message)
    {
        $token = env('WHATSAPP_TOKEN');
        $phoneNumberId = env('WHATSAPP_PHONE_ID');

        if (!$token || !$phoneNumberId) {
            Log::error('❌ Missing WhatsApp credentials.');
            return;
        }

        $url = "https://graph.facebook.com/v22.0/{$phoneNumberId}/messages";

        $response = Http::withToken($token)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);

        if ($response->successful()) {
            Log::info('✅ WhatsApp message sent successfully.', $response->json());
        } else {
            Log::warning('⚠️ Failed to send WhatsApp message.', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        }
    }
}
