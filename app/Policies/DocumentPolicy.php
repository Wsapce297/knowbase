<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;

/**
 * Who may do what with documents.
 *
 * Rule of thumb: everything happens inside a team. Any member can read and
 * upload; deleting is for the person who uploaded it, or an owner/admin.
 */
class DocumentPolicy
{
    /**
     * May the user see the list of this team's documents?
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Document $document): bool
    {
        return $user->belongsToTeam($document->team);
    }

    /**
     * May the user upload a document into this team?
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function delete(User $user, Document $document): bool
    {
        $team = $document->team;

        if (! $user->belongsToTeam($team)) {
            return false;
        }

        return $document->uploaded_by === $user->id
            || $user->hasTeamRole($team, TeamRole::Owner, TeamRole::Admin);
    }
}
