<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Staff\Contracts\ServiceAssignments;

final class ServicesServiceAssignments implements ServiceAssignments
{
    public function __construct(
        private readonly ServiceRepository $services,
    ) {}

    /**
     * @param  list<string>  $staffMemberIds
     * @return list<string>
     */
    public function staffOfferingServices(string $businessId, array $staffMemberIds): array
    {
        if ($staffMemberIds === []) {
            return [];
        }

        $assigned = [];

        foreach ($this->services->activeForBusiness($businessId) as $service) {
            foreach ($service->staffIds() as $staffId) {
                $assigned[$staffId] = true;
            }
        }

        return array_values(array_filter(
            $staffMemberIds,
            static fn (string $staffMemberId): bool => isset($assigned[$staffMemberId]),
        ));
    }
}
