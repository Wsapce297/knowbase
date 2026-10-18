<?php

use App\Enums\TeamRole;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();

    $this->team = Team::factory()->ownedBy($this->owner)->create();
    $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);

    $this->conversation = Conversation::factory()->for($this->team)->for($this->member)->create();
});

test('the person who started a conversation can see, continue and delete it', function () {
    expect($this->member->can('view', $this->conversation))->toBeTrue()
        ->and($this->member->can('update', $this->conversation))->toBeTrue()
        ->and($this->member->can('delete', $this->conversation))->toBeTrue();
});

test('conversations are private, even from the team owner', function () {
    $outsider = User::factory()->create();

    expect($this->owner->can('view', $this->conversation))->toBeFalse()
        ->and($this->owner->can('delete', $this->conversation))->toBeFalse()
        ->and($outsider->can('view', $this->conversation))->toBeFalse()
        ->and($this->member->can('create', [Conversation::class, $this->team]))->toBeTrue()
        ->and($outsider->can('create', [Conversation::class, $this->team]))->toBeFalse();
});

test('people who leave the team lose access to their conversations there', function () {
    $this->team->members()->detach($this->member);

    expect($this->member->fresh()->can('view', $this->conversation->fresh()))->toBeFalse();
});
