<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's membership in a team (the team_user pivot row).
 *
 * @property TeamRole $role
 */
class Membership extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }
}
