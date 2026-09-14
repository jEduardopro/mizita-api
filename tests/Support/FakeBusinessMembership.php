<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessMembership;

final class FakeBusinessMembership implements BusinessMembership
{
    /**
     * @param  array<string, list<string>>  $businessIdsByAccount  account uuid => business uuids
     */
    public function __construct(
        private readonly array $businessIdsByAccount = [],
    ) {}

    /**
     * @return list<string>
     */
    public function businessIdsFor(string $accountId): array
    {
        return $this->businessIdsByAccount[$accountId] ?? [];
    }
}
