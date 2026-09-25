<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

final readonly class StartCalendarAuthorizationInput
{
    public function __construct(
        public string $accountId,
    ) {}
}
