<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/webhook', [WhatsAppController::class, 'verify']);

// Incoming messages (POST from Meta)
Route::post('/webhook', [WhatsAppController::class, 'receiveMessage']);
// Route::get('/webhook/whatsapp', function () {
//     return response()->json([
//         'status' => '✅ Webhook route is working!',
//         'time' => now()->toDateTimeString(),
//     ]);
// });