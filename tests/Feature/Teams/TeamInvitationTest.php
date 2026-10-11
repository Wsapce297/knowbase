<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->team = app(CreateTeam::class)->handle($this->owner, 'Acme Support');
});

test('an owner can invite someone by email', function () {
    Notification::fake();

    $this->actingAs($this->owner)
        ->post(route('team-invitations.store', $this->team), [
            'email' => 'New.Person@Example.com',
            'role' => 'admin',
        ])
        ->assertSessionHasNoErrors();

    $invitation = TeamInvitation::firstWhere('email', 'new.person@example.com');

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(TeamRole::Admin)
        ->and($invitation->inviter->is($this->owner))->toBeTrue();

    Notification::assertSentOnDemand(
        TeamInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new.person@example.com',
    );
});

test('members cannot invite people', function () {
    $member = User::factory()->create();
    $this->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->post(route('team-invitations.store', $this->team), ['email' => 'x@example.com', 'role' => 'member'])
        ->assertForbidden();
});

test('nobody can be invited to a personal team', function () {
    $personal = app(CreateTeam::class)->personal($this->owner);

    $this->actingAs($this->owner)
        ->post(route('team-invitations.store', $personal), ['email' => 'x@example.com', 'role' => 'member'])
        ->assertForbidden();
});

test('existing members cannot be invited again', function () {
    Notification::fake();

    $member = User::factory()->create(['email' => 'member@example.com']);
    $this->team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($this->owner)
        ->post(route('team-invitations.store', $this->team), ['email' => 'member@example.com', 'role' => 'member'])
        ->assertSessionHasErrors('email');
});

test('people cannot be invited as owner', function () {
    $this->actingAs($this->owner)
        ->post(route('team-invitations.store', $this->team), ['email' => 'x@example.com', 'role' => 'owner'])
        ->assertSessionHasErrors('role');
});

test('inviting the same email twice refreshes the invitation instead of duplicating it', function () {
    Notification::fake();

    foreach (['member', 'admin'] as $role) {
        $this->actingAs($this->owner)
            ->post(route('team-invitations.store', $this->team), ['email' => 'x@example.com', 'role' => $role]);
    }

    expect($this->team->invitations()->count())->toBe(1)
        ->and($this->team->invitations()->first()->role)->toBe(TeamRole::Admin);

    Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 2);
});

test('the invited person can accept the invitation', function () {
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = $this->team->invitations()->create(['email' => 'invitee@example.com', 'role' => TeamRole::Admin]);

    $this->actingAs($invitee)
        ->get(route('team-invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    expect($invitee->teamRole($this->team))->toBe(TeamRole::Admin)
        ->and($invitee->fresh()->current_team_id)->toBe($this->team->id)
        ->and(TeamInvitation::count())->toBe(0);
});

test('an invitation cannot be accepted by a different email address', function () {
    $invitation = $this->team->invitations()->create(['email' => 'invitee@example.com', 'role' => TeamRole::Member]);
    $someoneElse = User::factory()->create(['email' => 'someone@example.com']);

    $this->actingAs($someoneElse)
        ->get(route('team-invitations.accept', $invitation->token))
        ->assertForbidden();

    expect($this->team->hasMember($someoneElse))->toBeFalse();
});

test('expired invitations cannot be accepted', function () {
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = $this->team->invitations()->create([
        'email' => 'invitee@example.com',
        'role' => TeamRole::Member,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($invitee)
        ->get(route('team-invitations.accept', $invitation->token))
        ->assertStatus(410);
});

test('guests must log in before accepting an invitation', function () {
    $invitation = $this->team->invitations()->create(['email' => 'invitee@example.com', 'role' => TeamRole::Member]);

    $this->get(route('team-invitations.accept', $invitation->token))
        ->assertRedirect(route('login'));
});

test('an admin can cancel an invitation', function () {
    $invitation = $this->team->invitations()->create(['email' => 'x@example.com', 'role' => TeamRole::Member]);

    $this->actingAs($this->owner)
        ->delete(route('team-invitations.destroy', [$this->team, $invitation]))
        ->assertRedirect();

    expect(TeamInvitation::count())->toBe(0);
});

test('an invitation from another team cannot be cancelled through this team', function () {
    $otherTeam = Team::factory()->ownedBy(User::factory()->create())->create();
    $invitation = $otherTeam->invitations()->create(['email' => 'x@example.com', 'role' => TeamRole::Member]);

    $this->actingAs($this->owner)
        ->delete(route('team-invitations.destroy', [$this->team, $invitation]))
        ->assertNotFound();
});

test('the settings page lists pending invitations', function () {
    $this->team->invitations()->create(['email' => 'pending@example.com', 'role' => TeamRole::Member]);

    $this->actingAs($this->owner)
        ->get(route('team-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invitations', 1)
            ->where('invitations.0.email', 'pending@example.com')
        );
});
