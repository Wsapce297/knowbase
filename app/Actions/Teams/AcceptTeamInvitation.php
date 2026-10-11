<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptTeamInvitation
{
    public function handle(TeamInvitation $invitation, User $user): Team
    {
        return DB::transaction(function () use ($invitation, $user): Team {
            $team = $invitation->team;

            if (! $team->hasMember($user)) {
                $team->members()->attach($user, ['role' => $invitation->role->value]);
            }

            $invitation->delete();

            $user->switchTeam($team);

            return $team;
        });
    }
}
