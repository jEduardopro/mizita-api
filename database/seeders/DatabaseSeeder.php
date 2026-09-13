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
        // Reference data first: both catalogs are idempotent, so they are safe
        // on every environment and on every re-run. Membership roles belong
        // here because a staff member cannot be saved before the role it is
        // assigned exists.
        $this->call(IndustrySeeder::class);
        $this->call(StaffRoleSeeder::class);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
