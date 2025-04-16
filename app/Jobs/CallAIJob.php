<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CallAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
    protected $sender;

    public function __construct($message, $sender)
    {
        $this->message = $message;
        $this->sender = $sender;
    }

    public function handle()
    {
        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'message' => $this->message,
                'sender' => $this->sender,
            ]);

            if ($response->failed()) {
                throw new \Exception("Failed to call AI API");
            }

            // Handling the response and processing it
            $aiResponse = $response->json();
            // Process the AI response here...
        } catch (\Exception $e) {
            Log::error('Error calling AI API: ' . $e->getMessage());
            throw $e; // Re-throw the exception to be handled by Laravel's retry mechanism
        }
    }
}
