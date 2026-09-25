<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Domains\Accounts\ValueObjects\ClosedBusinessSnapshot;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;
use LogicException;

final class FakeOwnedBusinesses implements OwnedBusinesses
{
    /**
     * @var array<string, OwnedBusinessSnapshot>
     */
    private array $owned = [];

    /**
     * @var array<string, ClosedBusinessSnapshot>
     */
    private array $closedByOwner = [];

    /**
     * @var list<string>
     */
    public array $described = [];

    /**
     * @var list<array{businessId: string, ownerAccountId: string}>
     */
    public array $closures = [];

    /**
     * @var list<array{businessId: string, ownerAccountId: string}>
     */
    public array $reopenings = [];

    public function __construct(
        public readonly AccountJournal $journal = new AccountJournal,
    ) {}

    public function owning(OwnedBusinessSnapshot $business): self
    {
        $this->owned[$business->id] = $business;

        return $this;
    }

    public function closedFor(string $ownerAccountId, ClosedBusinessSnapshot $business): self
    {
        $this->closedByOwner[$ownerAccountId] = $business;

        return $this;
    }

    public function describe(string $businessId): OwnedBusinessSnapshot
    {
        $this->journal->record('ownedBusinesses.describe');
        $this->described[] = $businessId;

        return $this->owned[$businessId]
            ?? throw new LogicException("FakeOwnedBusinesses knows no business [{$businessId}].");
    }

    public function close(string $businessId, string $ownerAccountId): void
    {
        $this->journal->record('ownedBusinesses.close');
        $this->closures[] = ['businessId' => $businessId, 'ownerAccountId' => $ownerAccountId];
    }

    public function closedBusinessOf(string $accountId): ?ClosedBusinessSnapshot
    {
        $this->journal->record('ownedBusinesses.closedBusinessOf');

        return $this->closedByOwner[$accountId] ?? null;
    }

    public function reopen(string $businessId, string $ownerAccountId): void
    {
        $this->journal->record('ownedBusinesses.reopen');
        $this->reopenings[] = ['businessId' => $businessId, 'ownerAccountId' => $ownerAccountId];
    }
}
