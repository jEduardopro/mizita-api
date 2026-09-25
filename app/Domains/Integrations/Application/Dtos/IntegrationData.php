<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

use App\Domains\Integrations\ValueObjects\IntegrationCategory;
use App\Domains\Integrations\ValueObjects\IntegrationKey;

final readonly class IntegrationData
{
    public function __construct(
        public IntegrationKey $key,
        public IntegrationCategory $category,
        public ?CalendarConnectionData $connection,
    ) {}
}
