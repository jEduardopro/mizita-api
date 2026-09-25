<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Integrations\Contracts\BusinessProfiles;
use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;

final class BusinessesBusinessProfiles implements BusinessProfiles
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function profileOf(string $businessId): BusinessCalendarProfile
    {
        try {
            $business = $this->businesses->findById($businessId);
        } catch (BusinessNotFound $missing) {
            throw CalendarBusinessNotFound::withId($businessId, $missing);
        }

        return new BusinessCalendarProfile($business->name(), $business->timezone());
    }
}
