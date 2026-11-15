<?php

use App\Http\Controllers\Widgets\WidgetFrameController;
use App\Http\Controllers\Widgets\WidgetMessageController;
use App\Models\Widget;
use Illuminate\Support\Facades\Route;

/*
| Public routes used by the embeddable widget on other websites.
| Loaded from bootstrap/app.php with the small "embed" middleware group
| instead of "web": no sessions, cookies, CSRF or current-team handling.
*/

Route::get('widget/{key}', WidgetFrameController::class)
    ->where('key', Widget::KEY_PATTERN)
    ->name('widget.frame');
Route::post('widget/{key}/messages', [WidgetMessageController::class, 'store'])
    ->where('key', Widget::KEY_PATTERN)
    ->middleware('throttle:widget-messages')
    ->name('widget.messages.store');
