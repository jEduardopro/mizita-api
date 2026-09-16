<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Shared\Infrastructure\Media\BusinessScopedMediaOwner;

final class FakeMediaOwner implements BusinessScopedMediaOwner
{
    public const BUSINESS_KEY = 7;

    public int $timesAsked = 0;

    public function __construct(
        private readonly int $businessKey = self::BUSINESS_KEY,
    ) {}

    public function businessKey(): int
    {
        $this->timesAsked++;

        return $this->businessKey;
    }
}
