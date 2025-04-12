<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\AIChatHistory;

class AIChatController extends Controller
{
    public function getAIResponse(Request $request)
    {
        Log::info('🧠 AI Prompt received', [
            'sender_number' => $request->input('sender_number'),
            'message' => $request->input('message'),
        ]);

        // Validate required fields
        $validated = $request->validate([
            'sender_number' => 'required|string',
            'message' => 'required|string',
        ]);

        $senderNumber = $validated['sender_number'];
        $userMessage = $validated['message'];

        // Call to get AI response
        $aiResponse = $this->callAI($userMessage);

        Log::info('✅ AI response generated', [
            'ai_response' => $aiResponse,
        ]);

        // Store AI response and user message to keep track of chat history
        $this->storeChatHistory($senderNumber, $userMessage, $aiResponse);

        return response()->json(['status' => 'success', 'response' => $aiResponse], 200);
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
            ])->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('✅ Received response from OpenRouter', [
                    'response' => $data ?? []
                ]);

                $reply = $data['choices'][0]['message']['content'] ?? null;

                if (!$reply) {
                    Log::warning('⚠️ AI response missing content', ['data' => $data]);
                    return 'I didn’t understand that, could you rephrase?';
                }

                return $reply;
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

    private function storeChatHistory($senderNumber, $userMessage, $aiResponse)
    {
        try {
            AIChatHistory::create([
                'sender_number' => $senderNumber,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
            ]);

            Log::info('📚 Chat history saved', [
                'sender_number' => $senderNumber,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error saving chat history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
