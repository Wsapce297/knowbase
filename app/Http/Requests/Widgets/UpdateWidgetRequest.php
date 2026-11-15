<?php

namespace App\Http\Requests\Widgets;

use App\Widgets\Origin;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateWidgetRequest extends FormRequest
{
    public const MAX_ORIGINS = 10;

    public const MAX_GREETING_LENGTH = 200;

    /**
     * Checked before validation, so members get a 403 instead of validation errors.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageWidget', $this->route('team'));
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'greeting' => ['nullable', 'string', 'max:'.self::MAX_GREETING_LENGTH],
            // One website per line, typed into a textarea.
            'allowed_origins' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Extra checks that run after the rules above.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('allowed_origins')) {
                    return;
                }

                foreach ($this->originLines() as $line) {
                    if (Origin::normalize($line) === null) {
                        $validator->errors()->add(
                            'allowed_origins',
                            '"'.Str::limit($line, 60).'" isn\'t a website address we can use. '
                            .'Write the full address starting with https://, without a page path, e.g. https://example.com.',
                        );

                        return;
                    }
                }

                $count = count($this->origins());

                if ($count > self::MAX_ORIGINS) {
                    $validator->errors()->add('allowed_origins', 'You can add up to '.self::MAX_ORIGINS.' websites.');
                }

                if ($count === 0 && $this->boolean('enabled')) {
                    $validator->errors()->add('allowed_origins', 'Add at least one website before switching the widget on.');
                }
            },
        ];
    }

    /**
     * The cleaned, de-duplicated list of origins (call after validation has passed).
     *
     * @return list<string>
     */
    public function origins(): array
    {
        $origins = array_map(Origin::normalize(...), $this->originLines());

        return array_values(array_unique(array_filter($origins)));
    }

    /**
     * Non-empty lines from the textarea, trimmed.
     *
     * @return list<string>
     */
    private function originLines(): array
    {
        $lines = preg_split('/\R/', (string) $this->input('allowed_origins', '')) ?: [];

        return array_values(array_filter(array_map(trim(...), $lines), fn (string $line) => $line !== ''));
    }
}
