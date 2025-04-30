<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', [WebController::class, 'index'])->name('web.chat');
Route::post('/chat/send', [WebController::class, 'send'])->name('web.send');


// php artisan cache:clear
// php artisan config:clear
// php artisan route:clear
// php artisan view:clear
