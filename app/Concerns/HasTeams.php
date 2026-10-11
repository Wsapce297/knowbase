<?php

namespace App\Concerns;

use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasTeams
{
    /**
     * Runs automatically for any model using this trait ("boot" + trait name).
     * When a user deletes their account, clean up the teams they own.
     */
    public static function bootHasTeams(): void
    {
        static::deleting(function (self $user): void {
            foreach ($user->ownedTeams()->get() as $team) {
                $successor = $team->members()
                    ->whereKeyNot($user->id)
                    ->orderByRaw("case when team_user.role = 'admin' then 0 else 1 end")
                    ->orderBy('team_user.created_at')
                    ->first();

                // Personal teams, and teams nobody else is in, are removed.
                if ($team->personal_team || $successor === null) {
                    $team->delete();

                    continue;
                }

                // Shared teams are handed to an admin (or the longest-standing member).
                $team->members()->updateExistingPivot($successor->id, ['role' => TeamRole::Owner->value]);
            }
        });
    }

    /**
     * @return BelongsToMany<Team, $this, Membership, 'membership'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * @return BelongsToMany<Team, $this, Membership, 'membership'>
     */
    public function ownedTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('role', TeamRole::Owner->value);
    }

    public function personalTeam(): ?Team
    {
        return $this->ownedTeams()->where('personal_team', true)->first();
    }

    public function belongsToTeam(?Team $team): bool
    {
        return $team !== null && $this->teams()->whereKey($team->id)->exists();
    }

    public function teamRole(Team $team): ?TeamRole
    {
        return $this->teams()->whereKey($team->id)->first()?->membership->role;
    }

    public function hasTeamRole(Team $team, TeamRole ...$roles): bool
    {
        $role = $this->teamRole($team);

        return $role !== null && in_array($role, $roles, true);
    }

    public function isCurrentTeam(Team $team): bool
    {
        return (int) $this->current_team_id === (int) $team->id;
    }

    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill(['current_team_id' => $team->id])->save();
        $this->setRelation('currentTeam', $team);

        return true;
    }
}
