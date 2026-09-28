<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\BookingPreferencesAllowance;

final class FakeBookingPreferencesAllowance implements BookingPreferencesAllowance
{
    /**
     * @var list<string>
     */
    public array $askedBusinessIds = [];

    public function __construct(
        private readonly bool $includesBookingPreferences,
    ) {}

    public static function free(): self
    {
        return new self(false);
    }

    public static function complete(): self
    {
        return new self(true);
    }

    public function includesBookingPreferences(string $businessId): bool
    {
        $this->askedBusinessIds[] = $businessId;

        return $this->includesBookingPreferences;
    }
}
