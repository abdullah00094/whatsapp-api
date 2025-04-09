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
        $apiKey = env('OPENROUTER_API_KEY');
        if (!$apiKey) {
            Log::error('❌ Missing OpenRouter API key');
            return 'Sorry, I am unable to process your request right now.';
        }

        $url = 'https://openrouter.ai/api/v1/chat/completions';

        $payload = [
            'model' => 'qwen/qwen2.5-vl-32b-instruct:free',
            'messages' => [
                ['role' => 'system', 'content' => 'You are سنمور (Snmor), a friendly and helpful AI assistant. Always introduce yourself first in every new conversation, then engage in natural dialogue. Maintain a polite and approachable tone. Prioritize understanding user needs and provide clear, concise responses.'],
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
