<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

/**
 * Laravel links this policy to the Team model automatically (by name).
 * Use it with $user->can('update', $team) or Gate::authorize('update', $team).
 */
class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamRole($team, TeamRole::Owner, TeamRole::Admin);
    }

    /**
     * Invite and remove members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $user->hasTeamRole($team, TeamRole::Owner, TeamRole::Admin);
    }

    public function updateMemberRole(User $user, Team $team): bool
    {
        return $user->hasTeamRole($team, TeamRole::Owner);
    }

    public function delete(User $user, Team $team): bool
    {
        return ! $team->personal_team && $user->hasTeamRole($team, TeamRole::Owner);
    }
}
