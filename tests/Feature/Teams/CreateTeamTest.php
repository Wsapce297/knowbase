<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

test('creating a team makes the creator its owner and switches to it', function () {
    $user = User::factory()->create();

    $team = app(CreateTeam::class)->handle($user, 'Acme Support');

    expect($team->name)->toBe('Acme Support')
        ->and($team->personal_team)->toBeFalse()
        ->and($user->teamRole($team))->toBe(TeamRole::Owner)
        ->and($user->fresh()->current_team_id)->toBe($team->id);
});

test('personal teams are named after the user', function () {
    $user = User::factory()->create(['name' => 'Sara Khan']);

    $team = app(CreateTeam::class)->personal($user);

    expect($team->name)->toBe("Sara's Team")
        ->and($team->personal_team)->toBeTrue()
        ->and($user->personalTeam()->is($team))->toBeTrue();
});

test('teams with the same name get different slugs', function () {
    $user = User::factory()->create();

    $first = app(CreateTeam::class)->handle($user, 'Support');
    $second = app(CreateTeam::class)->handle($user, 'Support');

    expect($first->slug)->not->toBe($second->slug)
        ->and($first->slug)->toStartWith('support-');
});

test('registering creates a personal team', function () {
    $this->post('/register', [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::firstWhere('email', 'new@example.com');

    expect($user)->not->toBeNull()
        ->and($user->personalTeam())->not->toBeNull()
        ->and($user->current_team_id)->toBe($user->personalTeam()->id);
});

test('the registration listener never creates a second team', function () {
    $user = User::factory()->create();
    app(CreateTeam::class)->personal($user);

    event(new Registered($user));

    expect($user->teams()->count())->toBe(1);
});
