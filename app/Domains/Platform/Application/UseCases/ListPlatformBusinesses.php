<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\UseCases;

use App\Domains\Platform\Application\Dtos\ListPlatformBusinessesInput;
use App\Domains\Platform\Application\Dtos\PlatformBusinessData;
use App\Domains\Platform\Contracts\BusinessPlans;
use App\Domains\Platform\Contracts\PlatformBusinessDirectory;
use App\Domains\Platform\ValueObjects\PlatformBusinessRecord;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Paginated;

final class ListPlatformBusinesses
{
    public function __construct(
        private readonly PlatformBusinessDirectory $directory,
        private readonly BusinessPlans $plans,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<Paginated<PlatformBusinessData>>
     */
    public function handle(ListPlatformBusinessesInput $input): UseCaseResponse
    {
        try {
            $input->validate();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $page = $this->directory->page($input->toQuery());
        $plans = $this->plans->plansOf(self::businessIdsOn($page), $this->clock->now());

        return UseCaseResponse::success($page->map(
            static fn (PlatformBusinessRecord $business): PlatformBusinessData => PlatformBusinessData::fromRecord(
                $business,
                $plans[$business->id],
            ),
        ));
    }

    /**
     * @param  Paginated<PlatformBusinessRecord>  $page
     * @return list<string>
     */
    private static function businessIdsOn(Paginated $page): array
    {
        return array_values(array_map(
            static fn (PlatformBusinessRecord $business): string => $business->id,
            $page->items,
        ));
    }
}
