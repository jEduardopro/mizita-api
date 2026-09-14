<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessContext;

final class FakeBusinessContext implements BusinessContext
{
    public const BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';

    public function __construct(
        private readonly string $businessId = self::BUSINESS_ID,
    ) {}

    public function currentBusinessId(): string
    {
        return $this->businessId;
    }
}
