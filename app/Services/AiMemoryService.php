<?php 
namespace App\Services;

use App\Models\AiChatHistory;
use Illuminate\Support\Facades\Log;

class AiMemoryService
{
    public function getHistory(string $number): array
    {
        $history = AiChatHistory::where('sender_number', $number)
            ->whereNull('deleted_at')
            ->get();

        $messages = [];

        foreach ($history as $entry) {
            $messages[] = ['role' => 'user', 'content' => $entry->user_message];
            $messages[] = ['role' => 'assistant', 'content' => $entry->ai_response];
        }

        Log::info('📚 Loaded memory for sender', ['number' => $number, 'count' => count($messages)]);

        return $messages;
    }

    public function saveMessage(string $number, string $userMessage, string $aiResponse): void
    {
        AiChatHistory::create([
            'sender_number' => $number,
            'user_message' => $userMessage,
            'ai_response' => $aiResponse,
        ]);

        Log::info('💾 Saved chat message', ['number' => $number]);
    }

    public function clearMemory(string $number): void
    {
        AiChatHistory::where('sender_number', $number)
            ->whereNull('deleted_at')
            ->delete();

        Log::info('🧹 Cleared memory for sender', ['number' => $number]);
    }
}
