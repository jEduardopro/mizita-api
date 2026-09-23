<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingPolicy;
use App\Domains\PublicCatalog\ValueObjects\PublicBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicBusinessProfile;
use App\Domains\PublicCatalog\ValueObjects\PublicContact;
use App\Domains\PublicCatalog\ValueObjects\PublicLocation;
use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;
use App\Domains\PublicCatalog\ValueObjects\PublicScheduleEntry;
use App\Domains\PublicCatalog\ValueObjects\PublicService;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;

final readonly class PublicBusinessPageData
{
    /**
     * @param  list<PublicScheduleEntry>  $schedule
     * @param  list<PublicService>  $services
     * @param  list<PublicTeamMember>  $team
     */
    public function __construct(
        public PublicBusinessProfile $profile,
        public PublicBrand $brand,
        public array $schedule,
        public PublicOpenState $openState,
        public string $lastBookableDate,
        public array $services,
        public array $team,
        public ?PublicLocation $location,
        public PublicContact $contact,
        public ?PublicBookingPolicy $bookingPolicy,
        public GuestFormFields $contactFields,
    ) {}
}
