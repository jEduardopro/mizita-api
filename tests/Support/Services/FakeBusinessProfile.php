<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Contracts\BusinessProfile;

final class FakeBusinessProfile implements BusinessProfile
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    /**
     * @param  array<string, string>  $slugsByBusiness
     */
    public function __construct(
        private readonly string $slug = ServiceFixtures::BUSINESS_SLUG,
        private readonly array $slugsByBusiness = [],
    ) {}

    public function slugFor(string $businessId): string
    {
        $this->calls[] = $businessId;

        return $this->slugsByBusiness[$businessId] ?? $this->slug;
    }

    public function callCount(): int
    {
        return count($this->calls);
    }
}
