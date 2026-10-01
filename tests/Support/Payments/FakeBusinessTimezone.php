<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\BusinessTimezone;
use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use DateTimeZone;

final class FakeBusinessTimezone implements BusinessTimezone
{
    /**
     * @var array<string, string>
     */
    private array $zones = [];

    /**
     * @var list<string>
     */
    public array $lookups = [];

    public function add(string $businessId, string $zone): self
    {
        $this->zones[$businessId] = $zone;

        return $this;
    }

    public function timezoneOf(string $businessId): DateTimeZone
    {
        $this->lookups[] = $businessId;

        $zone = $this->zones[$businessId] ?? throw PaymentBusinessNotFound::withId($businessId);

        return new DateTimeZone($zone);
    }
}
