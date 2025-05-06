<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', [WebController::class, 'index'])->name('web.chat');
Route::post('/chat/send', [WebController::class, 'send'])->name('web.send');
Route::get('/download/presentation', function () {
    $path = storage_path('app/pdf/presentation.pdf');

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path); // or ->download($path, 'presentation.pdf');
});



// php artisan cache:clear
// php artisan config:clear
// php artisan route:clear
// php artisan view:clear
