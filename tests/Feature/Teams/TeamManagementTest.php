<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a user can create a team', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Acme Support'])
        ->assertRedirect(route('dashboard'));

    $team = Team::firstWhere('name', 'Acme Support');

    expect($team)->not->toBeNull()
        ->and($user->teamRole($team))->toBe(TeamRole::Owner)
        ->and($user->fresh()->current_team_id)->toBe($team->id);
});

test('a team name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a user can switch to a team they belong to', function () {
    $user = User::factory()->create();
    $first = app(CreateTeam::class)->handle($user, 'First');
    app(CreateTeam::class)->handle($user, 'Second');

    $this->actingAs($user)
        ->put(route('current-team.update'), ['team_id' => $first->id])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_team_id)->toBe($first->id);
});

test('a user cannot switch to a team they do not belong to', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->ownedBy(User::factory()->create())->create();

    $this->actingAs($user)
        ->put(route('current-team.update'), ['team_id' => $otherTeam->id])
        ->assertForbidden();
});

test('an admin can rename the team but a member cannot', function () {
    $owner = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Old Name');

    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->patch(route('teams.update', $team), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('teams.update', $team), ['name' => 'New Name'])
        ->assertRedirect();

    expect($team->fresh()->name)->toBe('New Name');
});

test('an owner can delete a team with their password', function () {
    $owner = User::factory()->create();
    $personal = app(CreateTeam::class)->personal($owner);
    $team = app(CreateTeam::class)->handle($owner, 'Temporary');

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $team), ['password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(Team::find($team->id))->toBeNull();

    // On the next request the owner is moved back to their personal team.
    $this->actingAs($owner->fresh())->get(route('dashboard'))->assertOk();

    expect($owner->fresh()->current_team_id)->toBe($personal->id);
});

test('deleting a team requires the correct password', function () {
    $owner = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Keep Me');

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $team), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(Team::find($team->id))->not->toBeNull();
});

test('a personal team cannot be deleted', function () {
    $owner = User::factory()->create();
    $personal = app(CreateTeam::class)->personal($owner);

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $personal), ['password' => 'password'])
        ->assertForbidden();
});

test('every page receives the current team and team list', function () {
    $user = User::factory()->create();
    app(CreateTeam::class)->personal($user);
    $team = app(CreateTeam::class)->handle($user, 'Acme Support');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teams.current.id', $team->id)
            ->where('teams.current.role', 'owner')
            ->has('teams.all', 2)
        );
});
