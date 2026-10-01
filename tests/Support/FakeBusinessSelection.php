<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessSelection;

final class FakeBusinessSelection implements BusinessSelection
{
    /**
     * @var list<string>
     */
    public array $forgotten = [];

    /**
     * @var list<array{string, string}>
     */
    public array $remembered = [];

    /**
     * @param  array<string, string>  $selectedBusinessIdByAccount
     */
    public function __construct(
        private array $selectedBusinessIdByAccount = [],
    ) {}

    public function selectedBusinessIdFor(string $accountId): ?string
    {
        return $this->selectedBusinessIdByAccount[$accountId] ?? null;
    }

    public function rememberFor(string $accountId, string $businessId): void
    {
        $this->remembered[] = [$accountId, $businessId];
        $this->selectedBusinessIdByAccount[$accountId] = $businessId;
    }

    public function forgetFor(string $accountId): void
    {
        $this->forgotten[] = $accountId;
        unset($this->selectedBusinessIdByAccount[$accountId]);
    }
}
