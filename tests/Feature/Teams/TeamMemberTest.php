<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = app(CreateTeam::class)->handle($this->owner, 'Acme Support');

    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();
    $this->team->members()->attach($this->admin, ['role' => TeamRole::Admin->value]);
    $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
});

test('the owner can change a member role', function () {
    $this->actingAs($this->owner)
        ->patch(route('team-members.update', [$this->team, $this->member]), ['role' => 'admin'])
        ->assertRedirect();

    expect($this->member->teamRole($this->team))->toBe(TeamRole::Admin);
});

test('admins cannot change roles', function () {
    $this->actingAs($this->admin)
        ->patch(route('team-members.update', [$this->team, $this->member]), ['role' => 'admin'])
        ->assertForbidden();
});

test('the owner role cannot be changed', function () {
    $this->actingAs($this->owner)
        ->patch(route('team-members.update', [$this->team, $this->owner]), ['role' => 'member'])
        ->assertForbidden();
});

test('an admin can remove a member but not another admin', function () {
    $otherAdmin = User::factory()->create();
    $this->team->members()->attach($otherAdmin, ['role' => TeamRole::Admin->value]);

    $this->actingAs($this->admin)
        ->delete(route('team-members.destroy', [$this->team, $this->member]))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->delete(route('team-members.destroy', [$this->team, $otherAdmin]))
        ->assertForbidden();

    expect($this->team->hasMember($this->member))->toBeFalse()
        ->and($this->team->hasMember($otherAdmin))->toBeTrue();
});

test('a member can leave the team', function () {
    $this->member->switchTeam($this->team);

    $this->actingAs($this->member)
        ->delete(route('team-members.destroy', [$this->team, $this->member]))
        ->assertRedirect(route('dashboard'));

    expect($this->team->hasMember($this->member))->toBeFalse();
});

test('the owner cannot leave or be removed', function () {
    $this->actingAs($this->owner)
        ->delete(route('team-members.destroy', [$this->team, $this->owner]))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('team-members.destroy', [$this->team, $this->owner]))
        ->assertForbidden();
});

test('removing someone who is not in the team returns not found', function () {
    $this->actingAs($this->owner)
        ->delete(route('team-members.destroy', [$this->team, User::factory()->create()]))
        ->assertNotFound();
});
