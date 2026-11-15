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
     * Invite people and cancel invitations. Personal teams are private, so no one can be invited.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return ! $team->personal_team
            && $user->hasTeamRole($team, TeamRole::Owner, TeamRole::Admin);
    }

    /**
     * Set up the website widget: switch it on or off, choose allowed websites, regenerate its key.
     * Personal teams may have one too (a solo user embedding it on their own site).
     */
    public function manageWidget(User $user, Team $team): bool
    {
        return $user->hasTeamRole($team, TeamRole::Owner, TeamRole::Admin);
    }

    public function updateMemberRole(User $user, Team $team): bool
    {
        return ! $team->personal_team && $user->hasTeamRole($team, TeamRole::Owner);
    }

    /**
     * Owners can remove anyone except themselves, admins can remove members,
     * and everyone except the owner can leave (remove themselves).
     */
    public function removeMember(User $user, Team $team, User $member): bool
    {
        $memberRole = $member->teamRole($team);

        if ($memberRole === null || $memberRole === TeamRole::Owner) {
            return false;
        }

        if ($member->is($user)) {
            return true;
        }

        return match ($user->teamRole($team)) {
            TeamRole::Owner => true,
            TeamRole::Admin => $memberRole === TeamRole::Member,
            default => false,
        };
    }

    public function delete(User $user, Team $team): bool
    {
        return ! $team->personal_team && $user->hasTeamRole($team, TeamRole::Owner);
    }
}
