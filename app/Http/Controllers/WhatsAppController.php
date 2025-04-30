<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhook;
use Illuminate\Http\Request;
use App\Models\AIChatHistory;
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

    /**
     * Handles incoming WhatsApp messages.
     */
    public function receiveMessage(Request $request)
    {
        Log::info('📩 Webhook message received', [
            'object' => $request->input('object'),
            'entry_id' => $request->input('entry.0.id'),
            'field' => $request->input('entry.0.changes.0.field'),
            'full_payload' => $request->all() // Add this line to log full payload
        ]);
    
        try {
            $data = $request->all();
            $change = $data['entry'][0]['changes'][0]['value'] ?? [];
    
            if (isset($change['messages'][0])) {
                $message = $change['messages'][0];
                $from = $message['from'] ?? null;
                $text = $message['text']['body'] ?? null;
    
                Log::info('📥 Message extracted', ['from' => $from, 'text' => $text]);
    
                if ($from && $text) {
                    // ✅ Dispatch job instead of calling AI directly
                    ProcessWebhook::dispatch($from, $text);
    
                    return response()->json(['status' => 'job_dispatched'], 200);
                } else {
                    Log::warning('⚠️ Incomplete message received', ['message' => $message]);
                }
            } elseif (isset($change['statuses'][0])) {
                $status = $change['statuses'][0];
                Log::info('📘 Status update received', [
                    'id' => $status['id'] ?? null,
                    'status' => $status['status'] ?? null,
                    'recipient_id' => $status['recipient_id'] ?? null
                ]);
            } else {
                Log::warning('⚠️ No message or status content found in payload.');
                return response()->json(['status' => 'no_content_found'], 200);
            }
    
            return response()->json(['status' => 'received'], 200);
        } catch (\Exception $e) {
            Log::error('❌ Exception during webhook handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
    


    



}
