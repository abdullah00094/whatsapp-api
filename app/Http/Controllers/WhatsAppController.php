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

        $verifyToken = config('services.whatsapp.verify_token');

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
        // Safe logging of limited payload info to avoid deep nesting
        Log::info('📩 Webhook message received', [
            'object' => $request->input('object'),
            'entry_id' => $request->input('entry.0.id'),
            'field' => $request->input('entry.0.changes.0.field')
        ]);

        try {
            $data = $request->all();
            $change = $data['entry'][0]['changes'][0]['value'] ?? [];

            // Check for message first
            if (isset($change['messages'][0])) {
                $message = $change['messages'][0];

                $from = $message['from'] ?? null; // Sender's phone number
                $text = $message['text']['body'] ?? null;

                Log::info('📥 Message extracted', [
                    'from' => $from,
                    'text' => $text
                ]);

                if ($from && $text) {
                    $aiResponse = $this->callAI($text);

                    \App\Models\AIChatHistory::create([
                        'sender_number' => $from,
                        'user_message' => $text,
                        'ai_response' => $aiResponse,
                    ]);

                    Log::info('✅ AI Response received', [
                        'to' => $from,
                        'message' => $text,
                        'ai_response' => $aiResponse
                    ]);

                    $this->sendMessage($from, $aiResponse);
                } else {
                    Log::warning('⚠️ Incomplete message received', ['message' => $message]);
                }
            }
            // Handle status updates (e.g., message delivered/read)
            elseif (isset($change['statuses'][0])) {
                $status = $change['statuses'][0];
                Log::info('📘 Status update received', [
                    'id' => $status['id'] ?? null,
                    'status' => $status['status'] ?? null,
                    'recipient_id' => $status['recipient_id'] ?? null
                ]);
            } else {
                Log::warning('⚠️ No message or status content found in payload.');
                return response()->json(['status' => 'no_content_found'], 200);
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

    private function callAI($message)
    {
        $apiKey = config('services.openrouter.key');

        if (!$apiKey) {
            Log::error('❌ Missing OpenRouter API key');
            return 'Sorry, I am unable to process your request right now.';
        }

        $url = 'https://openrouter.ai/api/v1/chat/completions';

        $payload = [
            'model' => 'deepseek/deepseek-chat:free',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<EOT
                You are مساعد, a smart and friendly AI assistant for JanPro, a B2B cleaning services company.

                Introduce yourself only once at the beginning of each new conversation. Then, engage in a natural, professional, and approachable tone. Prioritize understanding the customer's business needs and provide clear, concise responses.

                Instructions:
                - You represent JanPro, which provides professional cleaning services to businesses and organizations.
                - You are responsible for answering customer inquiries related to JanPro’s services.
                - If a user requests contact details for managers, provide the following static contact numbers:
                  • مدير الدعم: 0500000001  
                  • مدير المبيعات: 0500000002  
                - Proactively suggest services from the following static list when appropriate:
                  • تنظيف المكاتب والشركات  
                  • خدمات النظافة اليومية أو الأسبوعية  
                  • تنظيف ما بعد البناء  
                  • تعقيم الأسطح والمكاتب  
                  • تنظيف الأرضيات والسجاد باحترافية  
                - Maintain memory and context of the conversation during the interaction.
                - When the user thanks you for your help (e.g., saying "شكرًا" or "شكرًا مساعد"), clear the memory and reset the context.
                - Keep the conversation focused, relevant, and within the current business scope.
                EOT
                ],
                ['role' => 'user', 'content' => $message],
            ]
        ];

        try {
            Log::info('📤 Sending request to OpenRouter', [
                'url' => $url,
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://yourdomain.com', // replace with your real domain
            ])->post($url, $payload);

            // Log raw response body for debugging
            Log::debug('📄 Raw response body from OpenRouter: ' . $response->body());

            if ($response->successful()) {
                $data = $response->json();

                if (!is_array($data) || !isset($data['choices'][0]['message']['content'])) {
                    Log::warning('⚠️ Unexpected response format from OpenRouter', [
                        'response' => $data
                    ]);
                    return 'I didn’t understand that, could you rephrase?';
                }

                Log::info('✅ Received response from OpenRouter', [
                    'response' => $data
                ]);

                return $data['choices'][0]['message']['content'];
            }

            Log::warning('⚠️ OpenRouter API request failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return 'Sorry, I couldn’t fetch a response from the AI.';
        } catch (\Exception $e) {
            Log::error('❌ Exception while requesting OpenRouter', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 'Sorry, something went wrong while processing your request.';
        }
    }


    private function sendMessage($to, $message)
    {
        try {
            $token = config('services.whatsapp.token');
            $phoneNumberId = config('services.whatsapp.phone_number_id');


            if (!$token || !$phoneNumberId) {
                Log::error('❌ Missing WhatsApp credentials.');
                return ['error' => 'Missing credentials'];
            }

            $url = "https://graph.facebook.com/v22.0/{$phoneNumberId}/messages";

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
