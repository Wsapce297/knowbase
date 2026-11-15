<?php

namespace App\Enums;

/**
 * Where a question was asked.
 */
enum QuestionChannel: string
{
    case Chat = 'chat';     // the private chat page (signed-in team members)
    case Widget = 'widget'; // the website widget (anonymous visitors)

    public function label(): string
    {
        return match ($this) {
            self::Chat => 'Team chat',
            self::Widget => 'Website widget',
        };
    }
}
