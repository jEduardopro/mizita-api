<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BookingRulesAllowance;

final class FakeBookingRulesAllowance implements BookingRulesAllowance
{
    /**
     * @var list<string>
     */
    public array $consultations = [];

    private function __construct(private readonly bool $includesBookingRules) {}

    public static function onCompletePlan(): self
    {
        return new self(true);
    }

    public static function onFreePlan(): self
    {
        return new self(false);
    }

    public function includesBookingRules(string $businessId): bool
    {
        $this->consultations[] = $businessId;

        return $this->includesBookingRules;
    }
}
