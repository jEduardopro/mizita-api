<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

final readonly class CalendarGrant
{
    public function __construct(
        public string $accountEmail,
        public CalendarTokens $tokens,
    ) {}
}
