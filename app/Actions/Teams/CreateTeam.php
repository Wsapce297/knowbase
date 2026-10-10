<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTeam
{
    /**
     * Create a team, make the user its owner and switch them to it.
     */
    public function handle(User $owner, string $name, bool $personal = false): Team
    {
        return DB::transaction(function () use ($owner, $name, $personal): Team {
            $team = Team::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'personal_team' => $personal,
            ]);

            $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

            $owner->switchTeam($team);

            return $team;
        });
    }

    /**
     * Create the personal team every user gets, e.g. "Sara's Team".
     */
    public function personal(User $user): Team
    {
        $firstName = Str::of($user->name)->trim()->before(' ')->value();

        $name = $firstName !== '' ? "{$firstName}'s Team" : 'My Team';

        return $this->handle($user, $name, personal: true);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'team';

        return $base.'-'.Str::lower(Str::random(6));
    }
}
