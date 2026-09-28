<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarBackfillQueue;

final class FakeCalendarBackfillQueue implements CalendarBackfillQueue
{
    /**
     * @var list<array{businessId: string, connectionId: string}>
     */
    public array $scheduled = [];

    public function schedule(string $businessId, string $connectionId): void
    {
        $this->scheduled[] = ['businessId' => $businessId, 'connectionId' => $connectionId];
    }
}
