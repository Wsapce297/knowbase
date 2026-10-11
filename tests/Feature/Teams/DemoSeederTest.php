<?php

use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoSeeder;

test('the demo seeder creates a demo account with a shared team', function () {
    $this->seed(DemoSeeder::class);

    $demo = User::firstWhere('email', 'demo@example.com');
    $acme = $demo->teams()->where('teams.personal_team', false)->first();

    expect($demo->teams()->count())->toBe(2)
        ->and($acme->name)->toBe('Acme Support')
        ->and($acme->members()->count())->toBe(3);
});

test('running the demo seeder twice does not duplicate anything', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(User::where('email', 'demo@example.com')->count())->toBe(1)
        ->and(Team::count())->toBe(4);
});
