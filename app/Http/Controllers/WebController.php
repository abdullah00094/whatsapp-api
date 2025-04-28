<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'user_id' => 'required|string'
        ]);

        ProcessWebhook::dispatch('web', $request->user_id, $request->message);

        return response()->json(['status' => 'Message queued']);
    }

    public function getResponse($user_id)
    {
        $response = Cache::get("web-chat-response-{$user_id}");

        // Clear after retrieval to prevent repeats
        Cache::forget("web-chat-response-{$user_id}");

        return response()->json(['response' => $response]);
    }
}
