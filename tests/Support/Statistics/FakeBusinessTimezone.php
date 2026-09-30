<?php

declare(strict_types=1);

namespace Tests\Support\Statistics;

use App\Domains\Statistics\Contracts\BusinessTimezone;
use DateTimeZone;

final class FakeBusinessTimezone implements BusinessTimezone
{
    public const ZONE = 'America/Monterrey';

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function __construct(
        private readonly string $zone = self::ZONE,
    ) {}

    public function timezoneOf(string $businessId): DateTimeZone
    {
        $this->reads[] = $businessId;

        return new DateTimeZone($this->zone);
    }
}
