<?php

use App\Enums\TeamRole;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A team with an owner and a member, both currently on it.
 *
 * @return array{0: Team, 1: User, 2: User}
 */
function documentsPageTeam(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::factory()->ownedBy($owner)->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $owner->switchTeam($team);
    $member->switchTeam($team);

    return [$team, $owner, $member];
}

test('members see the documents of their current team only', function () {
    [$team, $owner, $member] = documentsPageTeam();
    Document::factory()->count(2)->for($team)->for($owner, 'uploader')->create();

    $otherOwner = User::factory()->create();
    $otherTeam = Team::factory()->ownedBy($otherOwner)->create();
    Document::factory()->for($otherTeam)->for($otherOwner, 'uploader')->create();

    $this->actingAs($member)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->has('documents', 2)
            ->where('permissions.canUpload', true)
            ->where('maxUploadMb', 10)
        );
});

test('each document includes its status and details', function () {
    [$team, $owner] = documentsPageTeam();
    $document = Document::factory()->ready()->for($team)->for($owner, 'uploader')->create([
        'title' => 'Refund Policy',
        'original_name' => 'refund-policy.pdf',
        'size' => 2048,
    ]);
    DocumentChunk::factory()->count(3)->for($document)->create();

    $this->actingAs($owner)
        ->get(route('documents.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents.0', fn (Assert $item) => $item
                ->where('id', $document->id)
                ->where('title', 'Refund Policy')
                ->where('originalName', 'refund-policy.pdf')
                ->where('size', 2048)
                ->where('status', 'ready')
                ->where('finished', true)
                ->where('error', null)
                ->where('chunksCount', 3)
                ->where('uploadedBy', $owner->name)
                ->where('canDelete', true)
                ->etc()
            )
        );
});

test('failed documents show their error', function () {
    [$team, $owner] = documentsPageTeam();
    Document::factory()->failed('No readable text was found.')->for($team)->for($owner, 'uploader')->create();

    $this->actingAs($owner)
        ->get(route('documents.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documents.0.status', 'failed')
            ->where('documents.0.error', 'No readable text was found.')
        );
});

test('members can only delete their own documents', function () {
    [$team, $owner, $member] = documentsPageTeam();
    $mine = Document::factory()->for($team)->for($member, 'uploader')->create(['created_at' => now()]);
    $theirs = Document::factory()->for($team)->for($owner, 'uploader')->create(['created_at' => now()->subMinute()]);

    $this->actingAs($member)
        ->get(route('documents.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documents.0.id', $mine->id)
            ->where('documents.0.canDelete', true)
            ->where('documents.1.id', $theirs->id)
            ->where('documents.1.canDelete', false)
        );
});

test('guests are sent to the login page', function () {
    $this->get(route('documents.index'))->assertRedirect(route('login'));
});
