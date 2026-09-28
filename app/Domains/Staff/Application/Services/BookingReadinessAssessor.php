<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Services;

use App\Domains\Staff\Contracts\ServiceAssignments;
use App\Domains\Staff\Contracts\WorkingHours;
use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use App\Domains\Staff\ValueObjects\BookingReadiness;

final class BookingReadinessAssessor
{
    public function __construct(
        private readonly ServiceAssignments $services,
        private readonly WorkingHours $workingHours,
    ) {}

    public function assess(string $businessId, string $staffMemberId): BookingReadiness
    {
        return $this->assessMany($businessId, [$staffMemberId])[$staffMemberId];
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @return array<string, BookingReadiness>
     */
    public function assessMany(string $businessId, array $staffMemberIds): array
    {
        if ($staffMemberIds === []) {
            return [];
        }

        $offeringServices = array_flip($this->services->staffOfferingServices($businessId, $staffMemberIds));
        $withWorkingHours = array_flip($this->workingHours->staffWithWorkingHours($businessId, $staffMemberIds));

        $readiness = [];

        foreach ($staffMemberIds as $staffMemberId) {
            $readiness[$staffMemberId] = BookingReadiness::blockedBy(...self::blockersOf(
                $staffMemberId,
                $offeringServices,
                $withWorkingHours,
            ));
        }

        return $readiness;
    }

    /**
     * @param  array<string, int>  $offeringServices
     * @param  array<string, int>  $withWorkingHours
     * @return list<BookingLinkBlocker>
     */
    private static function blockersOf(string $staffMemberId, array $offeringServices, array $withWorkingHours): array
    {
        $blockers = [];

        if (! isset($offeringServices[$staffMemberId])) {
            $blockers[] = BookingLinkBlocker::NoServices;
        }

        if (! isset($withWorkingHours[$staffMemberId])) {
            $blockers[] = BookingLinkBlocker::NoWorkingHours;
        }

        return $blockers;
    }
}
