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
        // The local account and its sample data, only in the local and testing environments;
        // everywhere else the user is created with `php artisan nook:user`.
        if (app()->environment(['local', 'testing'])) {
            $user = User::firstOrCreate(
                ['email' => 'ivayla@example.com'],
                ['name' => 'Ivayla', 'password' => 'password'],
            );

            $this->callWith(GroceryItemSeeder::class, ['user' => $user]);
            $this->callWith(PromoSeeder::class, ['user' => $user]);
            $this->callWith(TaskSeeder::class, ['user' => $user]);
        }
    }
}
