<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\CreateServiceInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Contracts\StaffDirectory;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Events\ServiceCreated;
use App\Domains\Services\Exceptions\ServiceNameAlreadyTaken;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Domains\Services\Services\SlugAllocator;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\Price;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\Slug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Contracts\Events\Dispatcher;

final class CreateService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly StaffDirectory $staff,
        private readonly ServicePresenter $presenter,
        private readonly SlugAllocator $slugs,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly BusinessContext $business,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<ServiceData>
     */
    public function handle(CreateServiceInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $service = $this->register($input, $businessId);
            $data = $this->presenter->describe($businessId, $service);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new ServiceCreated($service->id));

        return UseCaseResponse::success($data);
    }

    /**
     * @throws ServiceNameAlreadyTaken
     * @throws UnknownStaffMember
     */
    private function register(CreateServiceInput $input, string $businessId): Service
    {
        $name = trim($input->name);

        if ($this->services->existsByName($businessId, $name)) {
            throw ServiceNameAlreadyTaken::for($name);
        }

        $base = Slug::fromName($name);

        $service = Service::create(
            id: $this->ids->next(),
            businessId: $businessId,
            name: $name,
            slug: $this->slugs->allocate($base, $this->services->slugsMatching($businessId, $base->value)),
            description: $input->description,
            duration: Duration::ofMinutes($input->durationMinutes),
            buffer: Buffer::ofMinutes($input->bufferMinutes),
            price: Price::fromString($input->price),
            color: ServiceColor::from($input->color),
            active: $input->active,
            staffIds: $this->eligibleStaffIds($businessId, $input->staffIds),
            now: $this->clock->now(),
        );

        $this->services->save($service);

        return $service;
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

        if ($selected === []) {
            return [];
        }

        if (count($this->staff->membersOf($businessId, $selected)) !== count($selected)) {
            throw UnknownStaffMember::amongSelected();
        }

        return $selected;
    }
}
