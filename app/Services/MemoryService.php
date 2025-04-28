<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MemoryService
{
    public function getMemory($user)
    {
        return Cache::get("memory-{$user}", []);
    }

    public function saveMemory($user, $userMessage, $aiResponse)
    {
        $history = $this->getMemory($user);

        $history[] = "User: {$userMessage}";
        $history[] = "AI: {$aiResponse}";

        Cache::put("memory-{$user}", array_slice($history, -20), now()->addHours(6)); // keep last 20 entries
    }
}
