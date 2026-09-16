<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BusinessAddressBook;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use Throwable;

final class FakeBusinessAddressBook implements BusinessAddressBook
{
    /**
     * @var array<string, BusinessAddressSnapshot>
     */
    private array $addresses = [];

    private ?Throwable $replaceFailure = null;

    /**
     * @var list<array{businessId: string, address: BusinessAddressSnapshot}>
     */
    public array $replacements = [];

    /**
     * @var list<string>
     */
    public array $removals = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function store(string $businessId, BusinessAddressSnapshot $address): self
    {
        $this->addresses[$businessId] = $address;

        return $this;
    }

    public function failingOnReplace(Throwable $failure): self
    {
        $this->replaceFailure = $failure;

        return $this;
    }

    public function forBusiness(string $businessId): ?BusinessAddressSnapshot
    {
        $this->reads[] = $businessId;

        return $this->addresses[$businessId] ?? null;
    }

    public function replaceForBusiness(string $businessId, BusinessAddressSnapshot $address): void
    {
        if ($this->replaceFailure !== null) {
            throw $this->replaceFailure;
        }

        $this->addresses[$businessId] = $address;
        $this->replacements[] = ['businessId' => $businessId, 'address' => $address];
    }

    public function removeForBusiness(string $businessId): void
    {
        unset($this->addresses[$businessId]);

        $this->removals[] = $businessId;
    }

    public function wasWritten(): bool
    {
        return $this->replacements !== [] || $this->removals !== [];
    }
}
