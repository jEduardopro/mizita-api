<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessTeamKey;
use RuntimeException;

final class FakeBusinessTeamKey implements BusinessTeamKey
{
    /**
     * @param  array<string, int>  $teamKeysByBusiness
     */
    public function __construct(
        private readonly array $teamKeysByBusiness = [],
    ) {}

    public function teamKeyFor(string $businessId): int
    {
        return $this->teamKeysByBusiness[$businessId]
            ?? throw new RuntimeException("FakeBusinessTeamKey knows no team key for business [{$businessId}].");
    }
}
