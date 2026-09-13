<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Database\Seeder;

/**
 * Seeds the industry catalog.
 *
 * Idempotent: rows are matched on their key, so re-running updates the order
 * and never duplicates. It deliberately does not touch "active" either - a row
 * someone retired on purpose must stay retired across the next deployment.
 */
final class IndustrySeeder extends Seeder
{
    /** Positions are spaced so a new industry can be slotted between two without renumbering. */
    private const POSITION_STEP = 10;

    /**
     * The catalog, in the order it is offered: the source catalogue's English
     * alphabetical order, which is what the position column encodes.
     *
     * Two pairs read alike and are not: "beauty" is the broad beauty business
     * while "hair_salon" is the salon itself, and "restoration" is damage
     * restoration, not "restaurant".
     *
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

        // Assigned here rather than left to the model's creating hook, because
        // seeders run with model events muted.
        $industry->uuid ??= $this->ids->next();
        $industry->position = $position;

        $industry->save();
    }
}
