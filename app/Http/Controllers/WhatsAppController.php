<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{

    public function verify(Request $request)
    {
        Log::info('📡 Webhook verification attempt', [
            'query' => $request->query(),
        ]);

        $verifyToken = env('WHATSAPP_VERIFY_TOKEN');

        // Meta parameters
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('✅ Webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('❌ Webhook verification failed', [
            'mode' => $mode,
            'provided_token' => $token,
            'expected_token' => $verifyToken
        ]);

        return response('Invalid verification token', 403);
    }

    public function receiveMessage(Request $request)
    {
        Log::info('📩 Webhook message received', ['payload' => $request->all()]);

        try {
            $data = $request->all();
            $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

            if (!$message) {
                Log::warning('⚠️ No message content found in payload.');
                return response()->json(['status' => 'no_message_found'], 200);
            }

            $from = $message['from'] ?? null;
            $text = $message['text']['body'] ?? null;

            Log::info('📥 Message extracted', [
                'from' => $from,
                'text' => $text
            ]);

            if ($from && $text) {
                $response = $this->sendMessage($from, "👋 Hello! This is JanPro AI bot. You said: \"$text\"");

                Log::info('✅ Auto-reply sent', [
                    'to' => $from,
                    'message' => $text,
                    'response' => $response
                ]);
            } else {
                Log::warning('⚠️ Incomplete message received.', ['message' => $message]);
            }

            return response()->json(['status' => 'received'], 200);
        } catch (\Exception $e) {
            Log::error('❌ Exception during webhook handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function sendMessage($to, $message)
    {
        try {
            $token = env('WHATSAPP_TOKEN');
            $phoneNumberId = env('WHATSAPP_PHONE_ID'); // ✅ match your .env name

            if (!$token || !$phoneNumberId) {
                Log::error('❌ Missing WhatsApp credentials.');
                return ['error' => 'Missing credentials'];
            }

            $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

            Log::info('📤 Sending WhatsApp message', [
                'to' => $to,
                'message' => $message
            ]);

            $response = Http::withToken($token)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ]);

            if ($response->successful()) {
                Log::info('✅ Message sent successfully', $response->json());
            } else {
                Log::warning('⚠️ Failed to send message', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('❌ Exception while sending message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['error' => $e->getMessage()];
        }
    }
}
