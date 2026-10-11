<?php

use App\Http\Controllers\Teams\AcceptTeamInvitationController;
use App\Http\Controllers\Teams\CurrentTeamController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Controllers\Teams\TeamSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/team', [TeamSettingsController::class, 'edit'])->name('team-settings.edit');

    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::patch('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::put('current-team', [CurrentTeamController::class, 'update'])->name('current-team.update');

    // Invitations
    Route::post('teams/{team}/invitations', [TeamInvitationController::class, 'store'])
        ->name('team-invitations.store');
    Route::delete('teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])
        ->scopeBindings()
        ->name('team-invitations.destroy');
    Route::get('team-invitations/{token}', AcceptTeamInvitationController::class)
        ->name('team-invitations.accept');

    // Members
    Route::patch('teams/{team}/members/{user}', [TeamMemberController::class, 'update'])
        ->name('team-members.update');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])
        ->name('team-members.destroy');
});
