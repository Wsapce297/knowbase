<?php

namespace App\Http\Controllers\Widgets;

use App\Actions\Chat\SendMessage;
use App\Http\Controllers\Concerns\StreamsAnswers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Widgets\WidgetMessageRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Widgets\VisitorToken;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WidgetMessageController extends Controller
{
    use StreamsAnswers;

    /**
     * Answer a website visitor's question, streamed with the same events as the chat page.
     *
     * The final event:
     *   {"type":"done","message":{...},"visitorToken":"kbv_..."}
     * visitorToken is only sent when this question started a new conversation
     * (the server can't send it again later: it only keeps the hash). It is null for follow-ups.
     */
    public function store(WidgetMessageRequest $request, SendMessage $sendMessage): StreamedResponse
    {
        $widget = $request->widget();
        $question = $request->validated('question');
        $conversation = $request->conversation();
        $visitorToken = null;

        if ($conversation === null) {
            $visitorToken = VisitorToken::generate();

            $conversation = Conversation::forceCreate([
                'team_id' => $widget->team_id,
                'widget_id' => $widget->id,
                'visitor_token_hash' => VisitorToken::hash($visitorToken),
                'title' => Str::limit(Str::squish($question), 80),
            ]);
        }

        return $this->streamAnswer(
            $sendMessage,
            $conversation,
            $question,
            isNew: $visitorToken !== null,
            done: fn (ConversationMessage $reply): array => [
                'message' => $this->publicMessage($reply),
                'visitorToken' => $visitorToken,
            ],
        );
    }

    /**
     * What a public visitor may see of an answer.
     *
     * Unlike the chat page, only the cited sources are sent, and only their title and page:
     * the team's documents may be internal, so the raw passages, document ids and
     * similarity scores stay on the server.
     *
     * @return array<string, mixed>
     */
    private function publicMessage(ConversationMessage $reply): array
    {
        return [
            'id' => $reply->id,
            'role' => $reply->role->value,
            'content' => $reply->content,
            'sources' => collect($reply->sources ?? [])
                ->filter(fn (array $source): bool => $source['cited'] === true)
                ->map(fn (array $source): array => [
                    'number' => $source['number'],
                    'documentTitle' => $source['documentTitle'],
                    'page' => $source['page'],
                ])
                ->values()
                ->all(),
            'createdAt' => $reply->created_at?->toIso8601String(),
        ];
    }
}
