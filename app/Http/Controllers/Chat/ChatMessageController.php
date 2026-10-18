<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\Conversation;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatMessageController extends Controller
{
    /**
     * Answer a question as a stream of "server-sent events" (SSE).
     *
     * The browser receives lines like:
     *   data: {"type":"text","text":"You can"}
     *   data: {"type":"text","text":" get a refund"}
     *   data: {"type":"done","conversationId":12,"message":{...}}
     * or, if something fails:
     *   data: {"type":"error","message":"The AI service is very busy..."}
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

        return response()->stream(function () use ($sendMessage, $conversation, $question, $isNew): void {
            set_time_limit(120); // a slow answer must not hit PHP's 30-second default

            try {
                $reply = $sendMessage->stream($conversation, $question, function (string $text): void {
                    $this->sendEvent(['type' => 'text', 'text' => $text]);
                });

                $this->sendEvent([
                    'type' => 'done',
                    'conversationId' => $conversation->id,
                    'message' => $reply->toChatArray(),
                ]);
            } catch (Throwable $e) {
                report($e); // full details go to storage/logs/laravel.log

                if ($isNew) {
                    $conversation->delete(); // don't leave an empty conversation behind
                }

                $this->sendEvent(['type' => 'error', 'message' => $this->friendlyError($e)]);
            }
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform', // stop proxies from holding events back
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Write one event and push it to the browser immediately.
     *
     * @param  array<string, mixed>  $data
     */
    private function sendEvent(array $data): void
    {
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }

    private function friendlyError(Throwable $e): string
    {
        return match (true) {
            $e instanceof RateLimitedException => 'The AI service has reached its usage limit. Please wait a minute and try again.',
            $e instanceof ProviderOverloadedException => 'The AI service is very busy right now. Please try again in a moment.',
            $e instanceof ProviderConnectionException => 'The AI service could not be reached. Please try again later.',
            default => 'Something went wrong while answering. Please try again.',
        };
    }
}
