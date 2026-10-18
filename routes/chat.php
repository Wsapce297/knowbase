<?php

use App\Http\Controllers\Chat\ChatMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // At most 10 questions per minute per user, to protect the free AI quota.
    Route::post('chat/messages', [ChatMessageController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('chat.messages.store');
});
