<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the "teams" data that every Inertia page receives,
 * used by the Vue team switcher.
 */
class SharedTeamData
{
    /**
     * @return array{current: array<string, mixed>|null, all: list<array<string, mixed>>}|null
     */
    public static function for(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $teams = $user->teams()->orderBy('teams.name')->get();

        return [
            'current' => $user->currentTeam ? self::team($user->currentTeam, $teams) : null,
            'all' => $teams->map(fn (Team $team) => self::team($team, $teams))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return array<string, mixed>
     */
    private static function team(Team $team, $teams): array
    {
        $role = $teams->firstWhere('id', $team->id)?->membership->role;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'personal' => $team->personal_team,
            'role' => $role?->value,
        ];
    }
}
