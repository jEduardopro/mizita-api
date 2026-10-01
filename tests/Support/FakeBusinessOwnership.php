<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessOwnership;

final class FakeBusinessOwnership implements BusinessOwnership
{
    /**
     * @var list<string>
     */
    public array $lookups = [];

    /**
     * @param  list<string>  $accountsOwningAnOpenBusiness
     */
    public function __construct(
        private readonly array $accountsOwningAnOpenBusiness = [],
    ) {}

    public function ownsOpenBusiness(string $accountId): bool
    {
        $this->lookups[] = $accountId;

        return in_array($accountId, $this->accountsOwningAnOpenBusiness, strict: true);
    }
}
