<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

test('a team can have members with roles', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::factory()->ownedBy($owner)->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect($team->members)->toHaveCount(2)
        ->and($owner->teamRole($team))->toBe(TeamRole::Owner)
        ->and($member->teamRole($team))->toBe(TeamRole::Member)
        ->and($team->owner()->is($owner))->toBeTrue()
        ->and($member->hasTeamRole($team, TeamRole::Owner, TeamRole::Admin))->toBeFalse();
});

test('a user can switch to a team they belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->ownedBy($user)->create();

    expect($user->switchTeam($team))->toBeTrue()
        ->and($user->fresh()->current_team_id)->toBe($team->id)
        ->and($user->isCurrentTeam($team))->toBeTrue();
});

test('a user cannot switch to a team they do not belong to', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->ownedBy(User::factory()->create())->create();

    expect($user->switchTeam($otherTeam))->toBeFalse()
        ->and($user->fresh()->current_team_id)->toBeNull();
});

test('only owners and admins can manage a team', function () {
    expect(TeamRole::Owner->canManageTeam())->toBeTrue()
        ->and(TeamRole::Admin->canManageTeam())->toBeTrue()
        ->and(TeamRole::Member->canManageTeam())->toBeFalse();
});

test('invitations get a token and expiry automatically', function () {
    $team = Team::factory()->ownedBy(User::factory()->create())->create();

    $invitation = $team->invitations()->create([
        'email' => 'new@example.com',
        'role' => TeamRole::Member,
    ]);

    expect($invitation->token)->toHaveLength(64)
        ->and($invitation->isExpired())->toBeFalse()
        ->and($invitation->role)->toBe(TeamRole::Member);
});

test('deleting a team removes its memberships and invitations', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->ownedBy($owner)->create();
    $team->invitations()->create(['email' => 'x@example.com', 'role' => TeamRole::Member]);

    $team->delete();

    expect($owner->teams()->count())->toBe(0)
        ->and(TeamInvitation::count())->toBe(0);
});
