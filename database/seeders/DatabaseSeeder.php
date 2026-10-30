<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The local account; in production the user is created with `php artisan nook:user`.
        if (! app()->isProduction()) {
            User::firstOrCreate(
                ['email' => 'ivayla@example.com'],
                ['name' => 'Ivayla', 'password' => 'password'],
            );
        }
    }
}
