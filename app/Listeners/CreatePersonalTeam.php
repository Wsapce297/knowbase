<?php

namespace App\Listeners;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Laravel discovers this listener automatically because handle()
 * type-hints the Registered event, which Fortify fires on sign-up.
 */
class CreatePersonalTeam
{
    public function __construct(private CreateTeam $createTeam) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->teams()->exists()) {
            return;
        }

        $this->createTeam->personal($user);
    }
}
