<?php

namespace App\Services\Ai\Drivers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebDriver
{
    public function send($to, $message)
    {
        Log::info('🌐 Sending Web Response', [
            'to' => $to,
            'message' => $message
        ]);

        // In real case you would broadcast via websocket etc.
        Cache::put("web-chat-response-{$to}", $message, now()->addMinutes(5));
    }
}
