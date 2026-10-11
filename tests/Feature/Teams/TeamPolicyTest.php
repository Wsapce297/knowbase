<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->team = Team::factory()->ownedBy($this->owner)->create();
    $this->team->members()->attach($this->admin, ['role' => TeamRole::Admin->value]);
    $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
});

test('members can view the team but outsiders cannot', function () {
    expect($this->member->can('view', $this->team))->toBeTrue()
        ->and($this->outsider->can('view', $this->team))->toBeFalse();
});

test('owners and admins can update the team and manage members', function () {
    expect($this->owner->can('update', $this->team))->toBeTrue()
        ->and($this->admin->can('update', $this->team))->toBeTrue()
        ->and($this->admin->can('manageMembers', $this->team))->toBeTrue()
        ->and($this->member->can('update', $this->team))->toBeFalse()
        ->and($this->member->can('manageMembers', $this->team))->toBeFalse();
});

test('only owners can change roles and delete the team', function () {
    expect($this->owner->can('updateMemberRole', $this->team))->toBeTrue()
        ->and($this->owner->can('delete', $this->team))->toBeTrue()
        ->and($this->admin->can('updateMemberRole', $this->team))->toBeFalse()
        ->and($this->admin->can('delete', $this->team))->toBeFalse();
});

test('personal teams cannot be deleted', function () {
    $personal = Team::factory()->personal()->ownedBy($this->owner)->create();

    expect($this->owner->can('delete', $personal))->toBeFalse();
});
