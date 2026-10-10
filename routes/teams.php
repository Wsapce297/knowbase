<?php

use App\Http\Controllers\Teams\CurrentTeamController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/team', [TeamSettingsController::class, 'edit'])->name('team-settings.edit');

    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::patch('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::put('current-team', [CurrentTeamController::class, 'update'])->name('current-team.update');
});
