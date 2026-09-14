<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Database\Seeder;

final class IndustrySeeder extends Seeder
{
    private const POSITION_STEP = 10;

    /**
     * @var list<string>
     */
    private const KEYS = [
        'automotive',
        'barbershop',
        'beauty',
        'business_services',
        'cafe',
        'charity',
        'church',
        'cleaning',
        'clinic',
        'computers',
        'construction',
        'consulting',
        'contractor',
        'creative_services',
        'customer_success',
        'dentist',
        'designer',
        'developer',
        'education',
        'electrical',
        'enterprise',
        'government',
        'hair_salon',
        'handyman',
        'home_services',
        'hvac',
        'information_technology',
        'instructor',
        'landscaping',
        'legal',
        'nail_salon',
        'non_profit',
        'personal_use',
        'pet_services',
        'photography',
        'plumbing',
        'real_estate',
        'recruitment',
        'religious_organization',
        'remodeling',
        'restoration',
        'restaurant',
        'retail',
        'roofing',
        'sales',
        'shopping',
        'spa',
        'sports',
        'technology',
        'tutor',
        'writer',
        'other',
    ];

    public function __construct(
        private readonly IdGenerator $ids,
    ) {}

    public function run(): void
    {
        foreach (self::KEYS as $index => $key) {
            $this->upsert($key, $index * self::POSITION_STEP);
        }
    }

    private function upsert(string $key, int $position): void
    {
        $industry = IndustryModel::query()->firstOrNew(['key' => $key]);

        $industry->uuid ??= $this->ids->next();
        $industry->position = $position;

        $industry->save();
    }
}
