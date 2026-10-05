<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class ReferenceDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            IndustrySeeder::class,
            StateSeeder::class,
            PaymentMethodSeeder::class,
            AuthorizationSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
