<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use App\Shared\ValueObjects\PhoneNumber;

final readonly class BusinessSettingsData
{
    /**
     * @param  list<BusinessScheduleEntry>  $schedule
     * @param  list<BusinessLinkSnapshot>  $links
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $industryId,
        public string $timezone,
        public ?string $about,
        public ?string $contactEmail,
        public string $currencyCode,
        public ?string $logoUrl,
        public ?PhoneNumber $phone,
        public ?BusinessAddressSnapshot $address,
        public array $schedule,
        public array $links,
        public BookingPageSnapshot $bookingPage,
        public BookingPolicySnapshot $bookingPolicy,
    ) {}
}
