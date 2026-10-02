<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\TeamAccountSharing;
use App\Domains\Staff\ValueObjects\AccountSharing;

final class FakeTeamAccountSharing implements TeamAccountSharing
{
    /**
     * @var array<string, list<string>>
     */
    private array $memberships = [];

    /**
     * @var list<array{accountId: string, businessId: string}>
     */
    public array $lookups = [];

    /**
     * @var list<array{accountIds: list<string>, businessId: string}>
     */
    public array $batchLookups = [];

    public function memberOf(string $accountId, string ...$businessIds): self
    {
        $this->memberships[$accountId] = array_values(array_unique([
            ...($this->memberships[$accountId] ?? []),
            ...$businessIds,
        ]));

        return $this;
    }

    public function sharingOf(string $accountId, string $businessId): AccountSharing
    {
        $this->lookups[] = ['accountId' => $accountId, 'businessId' => $businessId];

        if ($this->isSharedOutside($accountId, $businessId)) {
            return AccountSharing::SharedWithOtherBusinesses;
        }

        return AccountSharing::ExclusiveToBusiness;
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function sharedAmong(array $accountIds, string $businessId): array
    {
        $this->batchLookups[] = ['accountIds' => array_values($accountIds), 'businessId' => $businessId];

        return array_values(array_unique(array_filter(
            $accountIds,
            fn (string $accountId): bool => $this->isSharedOutside($accountId, $businessId),
        )));
    }

    private function isSharedOutside(string $accountId, string $businessId): bool
    {
        return array_diff($this->memberships[$accountId] ?? [], [$businessId]) !== [];
    }
}
