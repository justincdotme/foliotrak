<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call(TagSeeder::class);
        $this->call(CareLookupSeeder::class);

        $email    = (string) env('FOLIOTRAK_ADMIN_EMAIL', 'admin@foliotrak.test');
        $password = (string) env('FOLIOTRAK_ADMIN_PASSWORD', 'testing123');

        // Never seed the default development account into production; an operator
        // who has not set their own credentials must not receive a known-password
        // admin.
        if (app()->environment('production') && $password === 'testing123') {
            return;
        }

        // Idempotent so the migrate service can seed on every boot without
        // tripping the unique email constraint on a re-run.
        User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'Admin',
                'password' => $password,
            ],
        );
    }
}
