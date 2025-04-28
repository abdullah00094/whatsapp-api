<?php

namespace App\Services;

use App\Services\MemoryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\SystemPromptService;
use App\Services\Ai\Drivers\WebDriver;
use App\Services\Drivers\WhatsAppDriver;

class AiService
{
    protected $memoryService;

    public function __construct(MemoryService $memoryService)
    {
        $this->memoryService = $memoryService;
    }

    public function processIncomingMessage($platform, $phone, $message)
    {
        Log::info('🧠 Processing incoming message', [
            'platform' => $platform,
            'phone' => $phone,
            'message' => $message,
        ]);

        $history = $this->memoryService->getMemory($phone);

        $systemPrompt = SystemPromptService::getPromptForPlatform($platform);

        $finalPrompt = $this->preparePrompt($systemPrompt, $history, $message);

        $response = $this->callAiApi($finalPrompt, $phone,  $platform);

        $this->memoryService->saveMemory($phone, $message, $response);

        return $response;
    }

    public function sendMessage($platform, $to, $message)
    {
        if ($platform === 'whatsapp') {
            (new WhatsAppDriver())->send($to, $message);
        } elseif ($platform === 'web') {
            (new WebDriver())->send($to, $message);
        } else {
            Log::error('❌ Unknown platform for sending message', [
                'platform' => $platform,
            ]);
        }
    }

    protected function preparePrompt($systemPrompt, $history, $message)
    {
        return $systemPrompt . "\n\n" . implode("\n", $history) . "\nUser: " . $message;
    }

    public function callAiApi(string $message, string $sender, string $platform): string
    {
        $apiKey = config('services.openrouter.key');
    
        if (!$apiKey) {
            Log::error('❌ Missing OpenRouter API key');
            return 'عذرًا، لا أستطيع تنفيذ طلبك الآن.';
        }
    
        // 🧹 Clear memory if the user says thanks
        // if (preg_match('/شكراً|شكرا|شكرًا مساعد/i', $message)) {
        //     $this->memoryService->clearMemory($sender);
        //     return 'على الرحب والسعة! إذا احتجت أي خدمة، أنا موجود دائمًا 🧽✨';
        // }
    
        $history = $this->memoryService->getMemory($sender);
    
        // Get platform-specific system prompt
        $systemContent = SystemPromptService::getPromptForPlatform($platform);
    
        $systemPrompt = [
            'role' => 'system',
            'content' => $systemContent
        ];
    
        $messages = array_merge(
            [$systemPrompt],
            $history,
            [['role' => 'user', 'content' => $message]]
        );
    
        $payload = [
            'model' => 'deepseek/deepseek-chat:free',
            'messages' => $messages,
            'temperature' => 0.8,
            'max_tokens' => 300,
        ];
    
        $url = 'https://openrouter.ai/api/v1/chat/completions';
    
        try {
            Log::info('📤 Sending request to OpenRouter', [
                'url' => $url,
                'payload' => $payload,
                'platform' => $platform
            ]);
    
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
            ])->timeout(30)->post($url, $payload);
    
            Log::debug('📄 Raw response from OpenRouter: ' . $response->body());
    
            if ($response->successful()) {
                $data = $response->json();
                $aiReply = $data['choices'][0]['message']['content'] ?? null;
    
                if (!$aiReply) {
                    Log::warning('⚠️ AI response missing', ['response' => $data]);
                    return 'لم أفهم تمامًا، ممكن توضح أكثر؟';
                }
    
                // 💾 Save to memory with platform context
                $this->memoryService->saveMemory($sender, $message, $aiReply, $platform);
    
                // Handle platform-specific actions
                $this->handlePlatformSpecificActions($aiReply, $sender, $platform);
    
                return trim($aiReply);
            }
    
            Log::warning('⚠️ OpenRouter API error', [
                'status' => $response->status(),
                'response' => $response->body(),
                'platform' => $platform
            ]);
    
            return 'عذرًا، النظام مشغول حاليًا. حاول بعد قليل.';
        } catch (\Exception $e) {
            Log::error('❌ Exception calling OpenRouter', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'platform' => $platform
            ]);
    
            return 'حدث خطأ أثناء معالجة الطلب. حاول مجددًا.';
        }
    }
    
    private function handlePlatformSpecificActions(string $aiReply, string $sender, string $platform)
    {
        // 📎 Handle PDF requests
        // if (preg_match('/\[send_presentation_pdf\]/', $aiReply)) {
        //     Log::info('📎 Presentation request detected', [
        //         'platform' => $platform,
        //         'sender' => $sender
        //     ]);
            
        //     $this->sendPresentationPdf($sender, $platform);
        //     $aiReply = preg_replace('/\[send_presentation_pdf\]/', '', $aiReply);
        // }
    
        // 📧 Handle complaint ready tag
        if (str_contains($aiReply, '[complaint_ready]')) {
            Log::info('📧 Complaint response ready', [
                'platform' => $platform,
                'sender' => $sender
            ]);
    
            app(ComplaintEmailService::class)->sendComplaintEmail(
                $aiReply,
                $sender,
                $platform
            );
        }
    }
}
