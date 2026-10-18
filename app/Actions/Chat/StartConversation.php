<?php

namespace App\Actions\Chat;

use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Start a new conversation with its first question.
 */
class StartConversation
{
    public function __construct(private SendMessage $sendMessage) {}

    public function handle(Team $team, User $user, string $question): Conversation
    {
        $conversation = Conversation::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => Str::limit(Str::squish($question), 80),
        ]);

        try {
            $this->sendMessage->handle($conversation, $question);
        } catch (Throwable $e) {
            $conversation->delete(); // don't leave an empty conversation behind

            throw $e;
        }

        return $conversation;
    }
}
