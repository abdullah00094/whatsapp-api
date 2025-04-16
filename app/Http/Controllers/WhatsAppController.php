<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhook;
use Illuminate\Http\Request;
use App\Services\AiMemoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{


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

    public function receiveMessage(Request $request, AiMemoryService $memoryService)
    {
        dispatch(new ProcessWebhook($memoryService , $request->all()));
    }
    

    /**
     * Handles incoming WhatsApp messages.
     */


}
