<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\UpcomingBookings;

final class FakeUpcomingBookings implements UpcomingBookings
{
    /**
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * @var list<string>
     */
    public array $businessesCounted = [];

    public function withUpcoming(string $businessId, int $count): self
    {
        $this->counts[$businessId] = $count;

        return $this;
    }

    public function countForBusiness(string $businessId): int
    {
        $this->businessesCounted[] = $businessId;

        return $this->counts[$businessId] ?? 0;
    }
}
