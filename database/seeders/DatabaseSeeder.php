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
        $this->call(SuperAdminSeeder::class);
        $this->call(CategorySeeder::class);

        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // The demo marketplace is a local-only file; include it when present so a fresh local seed keeps the review logins.
        if (app()->environment('local') && class_exists(DemoMarketplaceSeeder::class)) {
            $this->call(DemoMarketplaceSeeder::class);
        }
    }
}
