<?php

use App\Http\Controllers\Chat\ChatMessageController;
use App\Http\Controllers\Chat\ConversationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::get('chat/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('chat.show');
    Route::delete('chat/{conversation}', [ConversationController::class, 'destroy'])->whereNumber('conversation')->name('chat.destroy');

    // At most 10 questions per minute per user, to protect the free AI quota.
    Route::post('chat/messages', [ChatMessageController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('chat.messages.store');
});
