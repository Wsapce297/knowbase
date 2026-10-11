<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteTeamMember
{
    /**
     * Invite someone by email. Inviting the same address again refreshes
     * the existing invitation (new link, new expiry) instead of duplicating it.
     */
    public function handle(Team $team, User $inviter, string $email, TeamRole $role): TeamInvitation
    {
        $email = Str::lower(trim($email));

        $alreadyMember = $team->members()
            ->whereRaw('lower(users.email) = ?', [$email])
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the team.',
            ]);
        }

        $invitation = $team->invitations()->updateOrCreate(
            ['email' => $email],
            [
                'role' => $role,
                'invited_by' => $inviter->id,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
            ],
        );

        Notification::route('mail', $email)
            ->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }
}
