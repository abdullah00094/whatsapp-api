<?php

namespace App\Jobs;

use App\Services\AiService;
use App\Models\AiChatHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $platform;
    protected $phone;
    protected $message;

    public function __construct($platform, $phone, $message)
    {
        $this->platform = $platform;
        $this->phone = $phone;
        $this->message = $message;
    }

    public function handle(): void
    {
        Log::info('🔄 Starting ProcessWebhook job', [
            'platform' => $this->platform,
            'phone' => $this->phone,
            'message' => $this->message,
        ]);

        try {
            $aiService = app(AiService::class);

            $response = $aiService->processIncomingMessage(
                $this->platform,
                $this->phone,
                $this->message
            );

            Log::info('🤖 AI Response Generated', [
                'response' => $response,
            ]);

            AiChatHistory::create([
                'sender_number' => $this->phone,
                'user_message' => $this->message,
                'ai_response' => $response,
            ]);

            $aiService->sendMessage(
                $this->platform,
                $this->phone,
                $response
            );

            Log::info('✅ Response Sent Successfully', [
                'to' => $this->phone,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error Processing Webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
