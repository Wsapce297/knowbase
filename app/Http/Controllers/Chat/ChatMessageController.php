<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\SendMessage;
use App\Http\Controllers\Concerns\StreamsAnswers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatMessageController extends Controller
{
    use StreamsAnswers;

    /**
     * Answer a question from the chat page as a stream of server-sent events.
     * The final event is {"type":"done","conversationId":12,"message":{...}}.
     */
    public function store(SendMessageRequest $request, SendMessage $sendMessage): StreamedResponse
    {
        $question = $request->validated('question');
        $conversation = $request->conversation();
        $isNew = $conversation === null;

        $conversation ??= Conversation::create([
            'team_id' => $request->team()->id,
            'user_id' => $request->user()->id,
            'title' => Str::limit(Str::squish($question), 80),
        ]);

        return $this->streamAnswer($sendMessage, $conversation, $question, $isNew, fn (ConversationMessage $reply): array => [
            'conversationId' => $conversation->id,
            'message' => $reply->toChatArray(),
        ]);
    }
}
