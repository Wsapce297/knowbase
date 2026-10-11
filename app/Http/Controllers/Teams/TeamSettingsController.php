<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
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

        $canManageMembers = $user->can('manageMembers', $team);

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
                'canRemove' => $user->can('removeMember', [$team, $member]),
            ])->values()->all(),
            'invitations' => $canManageMembers
                ? $team->invitations()->latest()->get()->map(fn (TeamInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'expired' => $invitation->isExpired(),
                    'expiresAt' => $invitation->expires_at->toDateString(),
                ])->values()->all()
                : [],
            'assignableRoles' => collect(TeamRole::assignable())->map(fn (TeamRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->all(),
            'permissions' => [
                'canUpdate' => $user->can('update', $team),
                'canManageMembers' => $canManageMembers,
                'canUpdateMemberRole' => $user->can('updateMemberRole', $team),
                'canDelete' => $user->can('delete', $team),
            ],
        ]);
    }
}
