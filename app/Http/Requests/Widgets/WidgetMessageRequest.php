<?php

namespace App\Http\Requests\Widgets;

use App\Models\Conversation;
use App\Models\Widget;
use App\Widgets\VisitorToken;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A question from an anonymous visitor in the website widget:
 * either the first one (no visitor_token) or a follow-up (with the token from the first answer).
 */
class WidgetMessageRequest extends FormRequest
{
    /** Shorter than the chat page (2000): public visitors ask short questions. */
    public const MAX_LENGTH = 1000;

    private ?Widget $widget = null;

    private ?Conversation $conversation = null;

    /**
     * Runs before validation, so unknown keys get a 404 without revealing anything else.
     */
    public function authorize(): bool
    {
        $this->widget(); // 404 for unknown, disabled or old keys (same as the iframe page)

        return $this->comesFromKnowbase();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'visitor_token' => ['nullable', 'string', 'regex:'.VisitorToken::PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Type a question first.',
            'question.max' => 'Questions can be at most '.self::MAX_LENGTH.' characters.',
            'visitor_token.regex' => 'This conversation is no longer available. Please start a new one.',
        ];
    }

    /**
     * The enabled widget for the key in the URL.
     */
    public function widget(): Widget
    {
        return $this->widget ??= Widget::query()
            ->where('public_key', (string) $this->route('key'))
            ->where('enabled', true)
            ->first() ?? abort(404);
    }

    /**
     * The visitor's conversation, or null for a first question.
     * A token that doesn't match a conversation of THIS widget gives a 404.
     */
    public function conversation(): ?Conversation
    {
        $token = $this->validated('visitor_token');

        if ($token === null) {
            return null;
        }

        return $this->conversation ??= Conversation::query()
            ->where('widget_id', $this->widget()->id)
            ->where('visitor_token_hash', VisitorToken::hash($token))
            ->first() ?? abort(404, 'This conversation is no longer available. Please start a new one.');
    }

    /**
     * The embed routes have no session, so Laravel's usual "redirect back with errors"
     * can't work. Always answer with JSON instead, whatever the Accept header says.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * Only the widget iframe (on Knowbase's own origin) may post here.
     *
     * Browsers always send an Origin header with a POST, so another website's
     * page can't use a widget key directly and spend the team's quota.
     * Scripts like curl can leave the header out; the rate limits cover them.
     */
    private function comesFromKnowbase(): bool
    {
        $origin = $this->headers->get('Origin');

        return $origin === null
            || self::originOf($origin) === self::originOf((string) config('app.url'));
    }

    /**
     * "https://Knowbase.test:8443/path" → "https://knowbase.test:8443"
     */
    private static function originOf(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme'].'://'.$parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
