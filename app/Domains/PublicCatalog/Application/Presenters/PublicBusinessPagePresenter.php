<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Presenters;

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\Contracts\PublishedBookingHorizon;
use App\Domains\PublicCatalog\Contracts\PublishedBookingPolicy;
use App\Domains\PublicCatalog\Contracts\PublishedBrand;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedContact;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\Contracts\PublishedOpenState;
use App\Domains\PublicCatalog\Contracts\PublishedSchedule;
use App\Domains\PublicCatalog\Contracts\PublishedServices;
use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\PublicService;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;

final class PublicBusinessPagePresenter
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly PublishedBrand $brand,
        private readonly PublishedSchedule $schedule,
        private readonly PublishedOpenState $openState,
        private readonly PublishedBookingHorizon $bookingHorizon,
        private readonly PublishedServices $services,
        private readonly PublishedTeam $team,
        private readonly PublishedLocation $location,
        private readonly PublishedContact $contact,
        private readonly PublishedBookingPolicy $bookingPolicy,
    ) {}

    /**
     * @throws BusinessPageNotFound
     */
    public function describe(string $slug): PublicBusinessPageData
    {
        $profile = $this->businesses->findBySlug($slug);
        $team = $this->team->forBusiness($profile->id);

        return new PublicBusinessPageData(
            profile: $profile,
            brand: $this->brand->forBusiness($profile->id),
            schedule: $this->schedule->forBusiness($profile->id),
            openState: $this->openState->forBusiness($profile->id),
            lastBookableDate: $this->bookingHorizon->lastBookableDateFor($profile->id),
            services: $this->servicesStaffedBy($this->services->forBusiness($profile->id), $team),
            team: $team,
            location: $this->location->forBusiness($profile->id),
            contact: $this->contact->forBusiness($profile->id),
            bookingPolicy: $this->bookingPolicy->forBusiness($profile->id),
        );
    }

    /**
     * @param  list<PublicService>  $services
     * @param  list<PublicTeamMember>  $team
     * @return list<PublicService>
     */
    private function servicesStaffedBy(array $services, array $team): array
    {
        $bookableStaffIds = array_map(
            static fn (PublicTeamMember $member): string => $member->id,
            $team,
        );

        return array_map(
            static fn (PublicService $service): PublicService => $service->restrictedTo($bookableStaffIds),
            $services,
        );
    }
}
