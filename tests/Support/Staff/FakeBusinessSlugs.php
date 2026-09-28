<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\BusinessSlugs;
use RuntimeException;
use Tests\Support\FakeBusinessContext;

final class FakeBusinessSlugs implements BusinessSlugs
{
    /**
     * @var list<string>
     */
    public array $lookups = [];

    /**
     * @param  array<string, string>  $slugs
     */
    public function __construct(
        private readonly array $slugs = [FakeBusinessContext::BUSINESS_ID => StaffFixtures::BUSINESS_SLUG],
    ) {}

    public function slugOf(string $businessId): string
    {
        $this->lookups[] = $businessId;

        return $this->slugs[$businessId]
            ?? throw new RuntimeException("Business [{$businessId}] was not found.");
    }
}
