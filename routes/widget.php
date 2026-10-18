<?php

use App\Http\Controllers\Widgets\WidgetSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/widget', [WidgetSettingsController::class, 'edit'])->name('widget.edit');
    Route::put('teams/{team}/widget', [WidgetSettingsController::class, 'update'])->name('widget.update');
    Route::post('teams/{team}/widget/key', [WidgetSettingsController::class, 'regenerateKey'])->name('widget.regenerate-key');
});
