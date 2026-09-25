<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

final readonly class ListIntegrationsInput
{
    public function __construct(
        public string $accountId,
    ) {}
}
