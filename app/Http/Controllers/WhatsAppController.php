<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AiMemoryService;
use App\Models\AIChatHistory;

class WhatsAppController extends Controller
{
    private AiMemoryService $memoryService;

    public function __construct(AiMemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    /**
     * Verifies the webhook for WhatsApp.
     */
    public function verify(Request $request)
    {
        Log::info('📡 Webhook verification attempt', ['query' => $request->query()]);

        $verifyToken = config('services.whatsapp.verify_token');

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

    /**
     * Handles incoming WhatsApp messages.
     */
    public function receiveMessage(Request $request)
    {
        Log::info('📩 Webhook message received', [
            'object' => $request->input('object'),
            'entry_id' => $request->input('entry.0.id'),
            'field' => $request->input('entry.0.changes.0.field')
        ]);

        try {
            $data = $request->all();
            $change = $data['entry'][0]['changes'][0]['value'] ?? [];

            if (isset($change['messages'][0])) {
                $message = $change['messages'][0];
                $from = $message['from'] ?? null;
                $text = $message['text']['body'] ?? null;

                Log::info('📥 Message extracted', ['from' => $from, 'text' => $text]);

                if ($from && $text) {
                    $aiResponse = $this->callAI($text, $from);

                    AIChatHistory::create([
                        'sender_number' => $from,
                        'user_message' => $text,
                        'ai_response' => $aiResponse,
                    ]);

                    Log::info('✅ AI Response received', [
                        'to' => $from,
                        'user_message' => $text,
                        'ai_response' => $aiResponse
                    ]);

                    $this->sendMessage($from, $aiResponse);
                } else {
                    Log::warning('⚠️ Incomplete message received', ['message' => $message]);
                }
            } elseif (isset($change['statuses'][0])) {
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

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Send a reply back to the WhatsApp sender (placeholder).
     */
    private function sendMessage($to, $message)
    {
        try {
            $token = env('WHATSAPP_TOKEN');
            $phoneNumberId = env('WHATSAPP_PHONE_ID'); // ✅ match your .env name

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

    /**
     * Calls the AI API and handles memory.
     */
    private function callAI(string $message, string $sender): string
    {
        $apiKey = config('services.openrouter.key');

        if (!$apiKey) {
            Log::error('❌ Missing OpenRouter API key');
            return 'عذرًا، لا أستطيع تنفيذ طلبك الآن.';
        }

        // 🧹 Clear memory if the user says thanks
        if (preg_match('/شكراً|شكرا|شكرًا مساعد/i', $message)) {
            $this->memoryService->clearMemory($sender);
            return 'على الرحب والسعة! إذا احتجت أي خدمة، أنا موجود دائمًا 🧽✨';
        }

        $history = $this->memoryService->getHistory($sender);

        $systemPrompt = [
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
        ];

        $messages = array_merge([$systemPrompt], $history, [['role' => 'user', 'content' => $message]]);

        $payload = [
            'model' => 'deepseek/deepseek-chat:free',
            'messages' => $messages,
            'temperature' => 0.8,
            'max_tokens' => 300,
        ];

        $url = 'https://openrouter.ai/api/v1/chat/completions';

        try {
            Log::info('📤 Sending request to OpenRouter', ['url' => $url, 'payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'https://yourdomain.com', // ✅ Update this to your real domain
            ])->post($url, $payload);

            Log::debug('📄 Raw response from OpenRouter: ' . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                $aiReply = $data['choices'][0]['message']['content'] ?? null;

                if (!$aiReply) {
                    Log::warning('⚠️ AI response missing', ['response' => $data]);
                    return 'لم أفهم تمامًا، ممكن توضح أكثر؟';
                }

                $this->memoryService->saveMessage($sender, $message, $aiReply);
                return $aiReply;
            }

            Log::warning('⚠️ OpenRouter API error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return 'عذرًا، النظام مشغول حاليًا. حاول بعد قليل.';
        } catch (\Exception $e) {
            Log::error('❌ Exception calling OpenRouter', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 'حدث خطأ أثناء معالجة الطلب. حاول مجددًا.';
        }
    }
}
