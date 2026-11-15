<?php

namespace App\Http\Controllers\Concerns;

use App\Actions\Chat\SendMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Closure;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Streams an answer as "server-sent events" (SSE). Shared by the chat page and the widget.
 *
 * The browser receives lines like:
 *   data: {"type":"text","text":"You can"}
 *   data: {"type":"text","text":" get a refund"}
 *   data: {"type":"done", ...fields from $done...}
 * or, if something fails:
 *   data: {"type":"error","message":"The AI service is very busy..."}
 */
trait StreamsAnswers
{
    /**
     * @param  bool  $isNew  The conversation was created for this question (deleted again if answering fails).
     * @param  Closure(ConversationMessage): array<string, mixed>  $done  Extra fields for the "done" event.
     */
    protected function streamAnswer(
        SendMessage $sendMessage,
        Conversation $conversation,
        string $question,
        bool $isNew,
        Closure $done,
    ): StreamedResponse {
        return response()->stream(function () use ($sendMessage, $conversation, $question, $isNew, $done): void {
            set_time_limit(120); // a slow answer must not hit PHP's 30-second default

            try {
                $reply = $sendMessage->stream($conversation, $question, function (string $text): void {
                    $this->sendEvent(['type' => 'text', 'text' => $text]);
                });

                $this->sendEvent(['type' => 'done', ...$done($reply)]);
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
