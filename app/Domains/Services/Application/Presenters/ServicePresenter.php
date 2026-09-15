<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Presenters;

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Contracts\BusinessProfile;
use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Contracts\StaffDirectory;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\ValueObjects\StaffMemberSnapshot;
use App\Shared\ValueObjects\Paginated;

final class ServicePresenter
{
    public function __construct(
        private readonly StaffDirectory $staff,
        private readonly ServiceImages $images,
        private readonly BusinessProfile $businesses,
        private readonly BookingLinks $bookingLinks,
    ) {}

    public function describe(string $businessId, Service $service): ServiceData
    {
        return ServiceData::fromEntity(
            $service,
            $this->staff->membersOf($businessId, $service->staffIds()),
            $this->images->urlFor($service->id),
            $this->bookingLinks->forService($this->businesses->slugFor($businessId), $service->slug()),
        );
    }

    /**
     * @param  Paginated<Service>  $page
     * @return Paginated<ServiceData>
     */
    public function describePage(string $businessId, Paginated $page): Paginated
    {
        $imageUrls = $this->images->urlsFor(array_map(
            static fn (Service $service): string => $service->id,
            $page->items,
        ));

        $snapshots = $this->snapshotsById($businessId, $page->items);
        $businessSlug = $this->businesses->slugFor($businessId);

        return $page->map(fn (Service $service): ServiceData => ServiceData::fromEntity(
            $service,
            $this->staffOf($service, $snapshots),
            $imageUrls[$service->id] ?? null,
            $this->bookingLinks->forService($businessSlug, $service->slug()),
        ));
    }

    /**
     * @param  list<Service>  $services
     * @return array<string, StaffMemberSnapshot>
     */
    private function snapshotsById(string $businessId, array $services): array
    {
        $staffIds = [];

        foreach ($services as $service) {
            $staffIds = [...$staffIds, ...$service->staffIds()];
        }

        $snapshots = [];

        foreach ($this->staff->membersOf($businessId, array_values(array_unique($staffIds))) as $snapshot) {
            $snapshots[$snapshot->id] = $snapshot;
        }

        return $snapshots;
    }

    /**
     * @param  array<string, StaffMemberSnapshot>  $snapshots
     * @return list<StaffMemberSnapshot>
     */
    private function staffOf(Service $service, array $snapshots): array
    {
        $staff = [];

        foreach ($service->staffIds() as $staffId) {
            if (isset($snapshots[$staffId])) {
                $staff[] = $snapshots[$staffId];
            }
        }

        return $staff;
    }
}
