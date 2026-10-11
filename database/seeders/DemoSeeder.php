<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo login: demo@example.com / password
 * Safe to run more than once.
 */
class DemoSeeder extends Seeder
{
    public function run(CreateTeam $createTeam): void
    {
        $demo = $this->user('Demo User', 'demo@example.com');

        if ($demo->teams()->exists()) {
            $this->command?->info('Demo data already exists, skipping.');

            return;
        }

        $createTeam->personal($demo);
        $acme = $createTeam->handle($demo, 'Acme Support');

        foreach ([
            ['Alex Admin', 'admin@example.com', TeamRole::Admin],
            ['Mia Member', 'member@example.com', TeamRole::Member],
        ] as [$name, $email, $role]) {
            $user = $this->user($name, $email);

            if (! $user->teams()->exists()) {
                $createTeam->personal($user);
            }

            $acme->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
            $user->switchTeam($acme);
        }

        $this->command?->info('Demo account: demo@example.com / password');
    }

    private function user(string $name, string $email): User
    {
        return User::query()->firstWhere('email', $email)
            ?? User::factory()->create([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
            ]);
    }
}
