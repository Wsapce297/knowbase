<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the team settings page shows the current team and its members', function () {
    $owner = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Acme Support');
    $team->members()->attach(User::factory()->create(), ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->get(route('team-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Team')
            ->where('team.name', 'Acme Support')
            ->has('members', 2)
            ->where('permissions.canUpdate', true)
            ->where('permissions.canDelete', true)
        );
});

test('members see the team settings page without management permissions', function () {
    $owner = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Acme Support');

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member)
        ->get(route('team-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canUpdate', false)
            ->where('permissions.canManageMembers', false)
            ->where('permissions.canDelete', false)
        );
});

test('personal teams cannot be deleted from the settings page', function () {
    $user = User::factory()->create();
    app(CreateTeam::class)->personal($user);

    $this->actingAs($user)
        ->get(route('team-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.personal', true)
            ->where('permissions.canDelete', false)
        );
});

test('deleting an account removes their personal team and hands shared teams to an admin', function () {
    $owner = User::factory()->create();
    $personal = app(CreateTeam::class)->personal($owner);
    $shared = app(CreateTeam::class)->handle($owner, 'Shared');

    $member = User::factory()->create();
    $admin = User::factory()->create();
    $shared->members()->attach($member, ['role' => TeamRole::Member->value]);
    $shared->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $owner->delete();

    expect(Team::find($personal->id))->toBeNull()
        ->and(Team::find($shared->id))->not->toBeNull()
        ->and($admin->teamRole($shared))->toBe(TeamRole::Owner)
        ->and($member->teamRole($shared))->toBe(TeamRole::Member);
});

test('deleting an account removes shared teams that have no other members', function () {
    $owner = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Solo');

    $owner->delete();

    expect(Team::find($team->id))->toBeNull();
});
