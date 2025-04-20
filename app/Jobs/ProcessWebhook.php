<?php

namespace App\Jobs;

use App\Models\AiChatHistory;
use App\Models\ChatHistory;
use App\Services\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Revolution\Google\Sheets\Facades\Sheets;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $message;
    protected $to;

    /**
     * Create a new job instance.
     */
    public function __construct($to, $message)
    {
        $this->to = $to;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🔄 Processing WhatsApp message', [
            'phone' => $this->to,
            'message' => $this->message,
        ]);

        $aiResponse = app(AiService::class)->callAi($this->message, $this->to);

        Log::info("🤖 AI Response: $aiResponse");

        // AiChatHistory::create([
        //     'sender_number' => $this->to,
        //     'user_message' => $this->message,
        //     'ai_response' => $aiResponse,
        // ]);

        $this->sendMessage($this->to, $aiResponse);
    }

    private function sendMessage($to, $message)
    {
        try {
            $token = config('services.whatsapp.token');
            $phoneNumberId = config('services.whatsapp.phone_number_id'); // ✅ match your .env name

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
