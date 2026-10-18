<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;

/**
 * Conversations are private: only the person who started one can see,
 * continue or delete it, and only while they are still in that team.
 */
class ConversationPolicy
{
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->user_id === $user->id
            && $user->belongsToTeam($conversation->team);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
