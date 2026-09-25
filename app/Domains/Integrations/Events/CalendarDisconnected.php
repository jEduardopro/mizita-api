<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Events;

final readonly class CalendarDisconnected
{
    public function __construct(
        public string $connectionId,
        public string $businessId,
    ) {}
}
