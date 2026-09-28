<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\PausedBusinessAccess;

final class FakePausedBusinessAccess implements PausedBusinessAccess
{
    /**
     * @var list<string>
     */
    public array $lookups = [];

    /**
     * @param  array<string, list<string>>  $pausedBusinessIdsByAccount
     */
    public function __construct(
        private readonly array $pausedBusinessIdsByAccount = [],
    ) {}

    /**
     * @return list<string>
     */
    public function pausedBusinessIdsFor(string $accountId): array
    {
        $this->lookups[] = $accountId;

        return $this->pausedBusinessIdsByAccount[$accountId] ?? [];
    }
}
