<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * List the current team's documents (newest first).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = Team::findOrFail($user->current_team_id);

        Gate::authorize('viewAny', [Document::class, $team]);

        $documents = $team->documents()
            ->with('uploader:id,name')
            ->withCount('chunks')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'originalName' => $document->original_name,
                'size' => $document->size,
                'status' => $document->status->value,
                'statusLabel' => $document->status->label(),
                'finished' => $document->status->isFinished(),
                'error' => $document->error,
                'chunksCount' => $document->chunks_count,
                'uploadedBy' => $document->uploader?->name,
                'uploadedAt' => $document->created_at->diffForHumans(),
                'canDelete' => $user->can('delete', $document),
            ]);

        return Inertia::render('documents/Index', [
            'documents' => $documents,
            'permissions' => [
                'canUpload' => $user->can('create', [Document::class, $team]),
            ],
            'maxUploadMb' => StoreDocumentRequest::MAX_KILOBYTES / 1024,
        ]);
    }

    /**
     * Save an uploaded file, create its document record and
     * start processing it in the background.
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $team = $request->team();
        $file = $request->file('file');

        // Stored under a random name inside the team's own folder on the private "local" disk.
        $path = $file->store("teams/{$team->id}/documents", 'local');

        $document = $team->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $request->validated('title')
                ?: Str::headline(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        // Extract, chunk and embed on the queue so the upload request stays fast.
        ProcessDocument::dispatch($document);

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
