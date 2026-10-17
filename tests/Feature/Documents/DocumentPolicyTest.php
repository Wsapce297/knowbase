<?php

use App\Enums\TeamRole;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;

test('members can view and upload documents but outsiders cannot', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();

    $team = Team::factory()->ownedBy($owner)->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $document = Document::factory()->for($team)->create();

    expect($member->can('viewAny', [Document::class, $team]))->toBeTrue()
        ->and($member->can('view', $document))->toBeTrue()
        ->and($member->can('create', [Document::class, $team]))->toBeTrue()
        ->and($outsider->can('viewAny', [Document::class, $team]))->toBeFalse()
        ->and($outsider->can('view', $document))->toBeFalse()
        ->and($outsider->can('create', [Document::class, $team]))->toBeFalse();
});

test('only the uploader, admins and the owner can delete a document', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $uploader = User::factory()->create();
    $otherMember = User::factory()->create();

    $team = Team::factory()->ownedBy($owner)->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($uploader, ['role' => TeamRole::Member->value]);
    $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);

    $document = Document::factory()->for($team)->for($uploader, 'uploader')->create();

    expect($uploader->can('delete', $document))->toBeTrue()
        ->and($admin->can('delete', $document))->toBeTrue()
        ->and($owner->can('delete', $document))->toBeTrue()
        ->and($otherMember->can('delete', $document))->toBeFalse();
});
