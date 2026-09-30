<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use App\Domains\Customers\Contracts\BusinessTimezone;
use DateTimeZone;

final class FakeBusinessTimezone implements BusinessTimezone
{
    public const ZONE = 'Europe/Madrid';

    /**
     * @var list<string>
     */
    public array $lookups = [];

    public function __construct(
        private readonly string $zone = self::ZONE,
    ) {}

    public function timezoneOf(string $businessId): DateTimeZone
    {
        $this->lookups[] = $businessId;

        return new DateTimeZone($this->zone);
    }
}
