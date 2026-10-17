<?php

use App\Enums\DocumentStatus;
use App\Enums\TeamRole;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * A shared team with an owner, an admin and a member, all currently on this team.
 *
 * @return array{0: Team, 1: User, 2: User, 3: User}
 */
function documentsTeam(): array
{
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::factory()->ownedBy($owner)->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    foreach ([$owner, $admin, $member] as $user) {
        $user->switchTeam($team);
    }

    return [$team, $owner, $admin, $member];
}

beforeEach(function () {
    // Files are written to a temporary fake disk, never to your real storage folder.
    Storage::fake('local');

    // Jobs are recorded instead of run, so uploads never start real processing here.
    Queue::fake();
});

test('a member can upload a markdown file', function () {
    [$team, , , $member] = documentsTeam();

    $this->actingAs($member)
        ->post(route('documents.store'), [
            'file' => UploadedFile::fake()->createWithContent('refund-policy.md', "# Refunds\n\nWithin 30 days."),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = Document::sole();

    expect($document->team_id)->toBe($team->id)
        ->and($document->uploaded_by)->toBe($member->id)
        ->and($document->title)->toBe('Refund Policy')
        ->and($document->original_name)->toBe('refund-policy.md')
        ->and($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->path)->toStartWith("teams/{$team->id}/documents/");

    Storage::disk('local')->assertExists($document->path);
});

test('uploading a document queues it for processing', function () {
    [, , , $member] = documentsTeam();

    $this->actingAs($member)->post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('faq.txt', 'Question and answer'),
    ]);

    $document = Document::sole();

    Queue::assertCount(1);
    Queue::assertPushed(ProcessDocument::class, fn (ProcessDocument $job): bool => $job->document->is($document));
});

test('a custom title can be given', function () {
    [, , , $member] = documentsTeam();

    $this->actingAs($member)->post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('notes.md', 'Some notes'),
        'title' => 'Onboarding Guide',
    ]);

    expect(Document::sole()->title)->toBe('Onboarding Guide');
});

test('pdf and text files are accepted', function () {
    [, $owner] = documentsTeam();

    $this->actingAs($owner)->post(route('documents.store'), [
        'file' => UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('faq.txt', 'Question and answer'),
    ])->assertSessionHasNoErrors();

    expect(Document::count())->toBe(2);
});

test('other file types are rejected', function () {
    [, $owner] = documentsTeam();

    $this->actingAs($owner)
        ->post(route('documents.store'), [
            'file' => UploadedFile::fake()->create('photo.png', 100, 'image/png'),
        ])
        ->assertSessionHasErrors(['file' => 'Upload a PDF, Markdown (.md) or text (.txt) file.']);

    expect(Document::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('files larger than 10 MB are rejected', function () {
    [, $owner] = documentsTeam();

    $this->actingAs($owner)
        ->post(route('documents.store'), [
            'file' => UploadedFile::fake()->create('huge.pdf', 10 * 1024 + 1, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['file' => 'The file may not be larger than 10 MB.']);

    expect(Document::count())->toBe(0);
});

test('a file is required', function () {
    [, $owner] = documentsTeam();

    $this->actingAs($owner)
        ->post(route('documents.store'), ['title' => 'No file here'])
        ->assertSessionHasErrors('file');
});

test('guests cannot upload', function () {
    $this->post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('notes.md', 'Some notes'),
    ])->assertRedirect(route('login'));

    expect(Document::count())->toBe(0);
});

test('uploads go to the current team', function () {
    [$team, $owner] = documentsTeam();
    $otherTeam = Team::factory()->ownedBy($owner)->create();
    $owner->switchTeam($otherTeam);

    $this->actingAs($owner)->post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('notes.md', 'Some notes'),
    ]);

    expect(Document::sole()->team_id)->toBe($otherTeam->id)
        ->and($team->documents()->count())->toBe(0);
});

test('the uploader can delete their own document', function () {
    [$team, , , $member] = documentsTeam();
    $document = Document::factory()->for($team)->for($member, 'uploader')->create();
    DocumentChunk::factory()->count(2)->for($document)->create();
    Storage::disk('local')->put($document->path, 'content');

    $this->actingAs($member)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect();

    expect(Document::count())->toBe(0)
        ->and(DocumentChunk::count())->toBe(0);

    Storage::disk('local')->assertMissing($document->path);
});

test('an admin can delete any document in the team', function () {
    [$team, , $admin, $member] = documentsTeam();
    $document = Document::factory()->for($team)->for($member, 'uploader')->create();

    $this->actingAs($admin)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect();

    expect(Document::count())->toBe(0);
});

test('a member cannot delete someone else\'s document', function () {
    [$team, , $admin, $member] = documentsTeam();
    $document = Document::factory()->for($team)->for($admin, 'uploader')->create();
    Storage::disk('local')->put($document->path, 'content');

    $this->actingAs($member)
        ->delete(route('documents.destroy', $document))
        ->assertForbidden();

    expect(Document::count())->toBe(1);
    Storage::disk('local')->assertExists($document->path);
});

test('people outside the team cannot delete a document', function () {
    [$team, $owner] = documentsTeam();
    $document = Document::factory()->for($team)->for($owner, 'uploader')->create();

    $outsider = User::factory()->create();
    $outsider->switchTeam(Team::factory()->ownedBy($outsider)->create());

    $this->actingAs($outsider)
        ->delete(route('documents.destroy', $document))
        ->assertForbidden();

    expect(Document::count())->toBe(1);
});
