<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

interface CalendarBackfillQueue
{
    public function schedule(string $businessId, string $connectionId): void;
}
