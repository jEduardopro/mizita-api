<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Presenters;

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\Contracts\PublishedBrand;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedContact;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\Contracts\PublishedSchedule;
use App\Domains\PublicCatalog\Contracts\PublishedServices;
use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;

final class PublicBusinessPagePresenter
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly PublishedBrand $brand,
        private readonly PublishedSchedule $schedule,
        private readonly PublishedServices $services,
        private readonly PublishedTeam $team,
        private readonly PublishedLocation $location,
        private readonly PublishedContact $contact,
    ) {}

    /**
     * @throws BusinessPageNotFound
     */
    public function describe(string $slug): PublicBusinessPageData
    {
        $profile = $this->businesses->findBySlug($slug);

        return new PublicBusinessPageData(
            profile: $profile,
            brand: $this->brand->forBusiness($profile->id),
            schedule: $this->schedule->forBusiness($profile->id),
            services: $this->services->forBusiness($profile->id),
            team: $this->team->forBusiness($profile->id),
            location: $this->location->forBusiness($profile->id),
            contact: $this->contact->forBusiness($profile->id),
        );
    }
}
