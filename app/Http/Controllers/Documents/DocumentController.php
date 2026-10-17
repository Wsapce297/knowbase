<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Save an uploaded file and create its document record.
     * (Part 7 will also start the processing job here.)
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $team = $request->team();
        $file = $request->file('file');

        // Stored under a random name inside the team's own folder on the private "local" disk.
        $path = $file->store("teams/{$team->id}/documents", 'local');

        $team->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $request->validated('title')
                ?: Str::headline(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back();
    }

    /**
     * Delete a document, its stored file and (via the database) its chunks.
     */
    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        Storage::disk($document->disk)->delete($document->path);

        $document->delete();

        return back();
    }
}
