<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

it('lets owners and admins manage the widget, but not members', function (TeamRole $role, bool $allowed) {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    expect($user->can('manageWidget', $team))->toBe($allowed);
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
]);

it('does not let outsiders manage the widget', function () {
    $team = Team::factory()->ownedBy(User::factory()->create())->create();

    expect(User::factory()->create()->can('manageWidget', $team))->toBeFalse();
});

it('lets the owner of a personal team manage its widget', function () {
    $user = User::factory()->create();
    $team = Team::factory()->personal()->ownedBy($user)->create();

    expect($user->can('manageWidget', $team))->toBeTrue();
});
