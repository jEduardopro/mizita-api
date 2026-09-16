<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Dtos\UpdateServiceInput;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Contracts\StaffDirectory;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\ServiceNameAlreadyTaken;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\ServiceRequiresStaff;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Domains\Services\Services\SlugAllocator;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\Price;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\Slug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class UpdateService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly StaffDirectory $staff,
        private readonly ServicePresenter $presenter,
        private readonly SlugAllocator $slugs,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<ServiceData>
     */
    public function handle(UpdateServiceInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $service = $this->services->findForBusiness($businessId, $input->serviceId);

            $this->apply($input, $service, $businessId);
            $this->services->save($service);

            return UseCaseResponse::success($this->presenter->describe($businessId, $service));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws ServiceNameAlreadyTaken
     * @throws ServiceNotFound
     * @throws ServiceRequiresStaff
     * @throws UnknownStaffMember
     */
    private function apply(UpdateServiceInput $input, Service $service, string $businessId): void
    {
        $this->renameIfChanged($input, $service, $businessId);

        $service->redescribe($input->description);
        $service->reschedule(
            Duration::ofMinutes($input->durationMinutes),
            Buffer::ofMinutes($input->bufferMinutes),
        );
        $service->reprice(Price::fromString($input->price));
        $service->recolor(ServiceColor::from($input->color));
        $service->assignStaff($this->eligibleStaffIds($businessId, $input->staffIds));

        $this->applyVisibility($service, $input->active);
    }

    /**
     * @throws ServiceNameAlreadyTaken
     */
    private function renameIfChanged(UpdateServiceInput $input, Service $service, string $businessId): void
    {
        $name = trim($input->name);

        if (mb_strtolower($name) === mb_strtolower($service->name())) {
            return;
        }

        if ($this->services->existsByName($businessId, $name)) {
            throw ServiceNameAlreadyTaken::for($name);
        }

        $base = Slug::fromName($name);
        $taken = array_values(array_filter(
            $this->services->slugsMatching($businessId, $base->value),
            static fn (string $slug): bool => $slug !== $service->slug(),
        ));

        $service->rename($name, $this->slugs->allocate($base, $taken));
    }

    private function applyVisibility(Service $service, bool $active): void
    {
        if ($active === $service->isActive()) {
            return;
        }

        if ($active) {
            $service->activate();

            return;
        }

        $service->deactivate();
    }

    /**
     * @param  list<string>  $staffIds
     * @return list<string>
     *
     * @throws UnknownStaffMember
     */
    private function eligibleStaffIds(string $businessId, array $staffIds): array
    {
        $selected = array_values(array_unique($staffIds));

        if (count($this->staff->membersOf($businessId, $selected)) !== count($selected)) {
            throw UnknownStaffMember::amongSelected();
        }

        return $selected;
    }
}
