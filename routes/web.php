<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('home');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Chat routes
    Route::get('chat', [ChatController::class, 'index'])->name('chat');
    Route::get('chat/{chatId}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('chat/send', [ChatController::class, 'send'])->name('chat.send');
});

require __DIR__.'/settings.php';
