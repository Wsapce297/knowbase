<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

test('users without any team get a personal team on their next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->refresh();

    expect($user->current_team_id)->not->toBeNull()
        ->and($user->currentTeam->personal_team)->toBeTrue();
});

test('users removed from their current team are moved to another team', function () {
    $user = User::factory()->create();
    $personal = app(CreateTeam::class)->personal($user);

    $other = Team::factory()->ownedBy(User::factory()->create())->create();
    $other->members()->attach($user, ['role' => TeamRole::Member->value]);
    $user->switchTeam($other);

    $other->members()->detach($user);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->current_team_id)->toBe($personal->id);
});
