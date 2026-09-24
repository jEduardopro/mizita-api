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
use Closure;

final class ServicePresenter
{
    public function __construct(
        private readonly StaffDirectory $staff,
        private readonly ServiceImages $images,
        private readonly BusinessProfile $businesses,
        private readonly BookingLinks $bookingLinks,
    ) {}

    public function describe(Service $service): ServiceData
    {
        $businessId = $service->businessId;

        return ServiceData::fromEntity(
            $service,
            $this->staff->membersOf($businessId, $service->staffIds()),
            $this->images->urlFor($businessId, $service->id),
            $this->bookingLinks->forService($this->businesses->slugFor($businessId), $service->slug()),
        );
    }

    /**
     * @param  Paginated<Service>  $page
     * @return Paginated<ServiceData>
     */
    public function describePage(string $businessId, Paginated $page): Paginated
    {
        return $page->map($this->describerFor($businessId, $page->items));
    }

    /**
     * @param  list<Service>  $services
     * @return list<ServiceData>
     */
    public function describeAll(string $businessId, array $services): array
    {
        return array_values(array_map($this->describerFor($businessId, $services), $services));
    }

    /**
     * @param  list<Service>  $services
     * @return Closure(Service): ServiceData
     */
    private function describerFor(string $businessId, array $services): Closure
    {
        $imageUrls = $this->images->urlsFor($businessId, array_map(
            static fn (Service $service): string => $service->id,
            $services,
        ));

        $snapshots = $this->snapshotsById($businessId, $services);
        $businessSlug = $this->businesses->slugFor($businessId);

        return fn (Service $service): ServiceData => ServiceData::fromEntity(
            $service,
            $this->staffOf($service, $snapshots),
            $imageUrls[$service->id] ?? null,
            $this->bookingLinks->forService($businessSlug, $service->slug()),
        );
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
