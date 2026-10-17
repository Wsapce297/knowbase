<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Largest allowed upload, in kilobytes (10 MB).
     */
    public const MAX_KILOBYTES = 10 * 1024;

    /**
     * Uploads always go into the user's current team.
     */
    public function team(): Team
    {
        return Team::query()->findOrFail($this->user()->current_team_id);
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', [Document::class, $this->team()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'extensions:pdf,md,txt', // the file name must end in .pdf, .md or .txt
                'mimes:pdf,md,txt',      // and its content must really be PDF or text
            ],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The file may not be larger than 10 MB.',
            'file.extensions' => 'Upload a PDF, Markdown (.md) or text (.txt) file.',
            'file.mimes' => 'Upload a PDF, Markdown (.md) or text (.txt) file.',
        ];
    }
}
