<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WebController;

Route::get('/chat', [WebController::class, 'index'])->name('chat.index');
Route::post('/chat/send', [WebController::class, 'send'])->name('chat.send');
Route::get('/chat/response/{user_id}', [WebController::class, 'getResponse']);
