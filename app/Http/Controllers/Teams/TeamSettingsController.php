<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamSettingsController extends Controller
{
    /**
     * Show the settings page for the user's current team.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam;

        Gate::authorize('view', $team);

        $members = $team->members()->orderBy('users.name')->get();

        return Inertia::render('settings/Team', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'personal' => $team->personal_team,
                'role' => $user->teamRole($team)?->value,
            ],
            'members' => $members->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->membership->role->value,
            ])->values()->all(),
            'permissions' => [
                'canUpdate' => $user->can('update', $team),
                'canManageMembers' => $user->can('manageMembers', $team),
                'canDelete' => $user->can('delete', $team),
            ],
        ]);
    }
}
