<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    public function __construct(public TeamInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter?->name ?? 'Someone';
        $role = $this->invitation->role->label();

        return (new MailMessage)
            ->subject("You're invited to join {$team->name} on ".config('app.name'))
            ->greeting('Hello!')
            ->line("{$inviter} invited you to join the {$team->name} team as {$role}.")
            ->action('Accept invitation', route('team-invitations.accept', $this->invitation->token))
            ->line('No account yet? Register with this email address first, verify it, then click the button again.')
            ->line('This invitation expires '.$this->invitation->expires_at->diffForHumans().'.');
    }
}
