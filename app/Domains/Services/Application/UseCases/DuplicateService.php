<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\UseCases;

use App\Domains\Services\Application\Dtos\DuplicateServiceInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Events\ServiceCreated;
use App\Domains\Services\Services\CopyNamer;
use App\Domains\Services\Services\SlugAllocator;
use App\Domains\Services\ValueObjects\Slug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Contracts\Events\Dispatcher;

final class DuplicateService
{
    private const COPY_SUFFIX = ' (Copy)';

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceImages $images,
        private readonly ServicePresenter $presenter,
        private readonly SlugAllocator $slugs,
        private readonly CopyNamer $copyNames,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly BusinessContext $business,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<ServiceData>
     */
    public function handle(DuplicateServiceInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $original = $this->services->findForBusiness($businessId, $input->serviceId);
            $copy = $this->duplicate($original, $input->name, $businessId);
            $data = $this->presenter->describe($businessId, $copy);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new ServiceCreated($copy->id));

        return UseCaseResponse::success($data);
    }

    private function duplicate(Service $original, ?string $submittedName, string $businessId): Service
    {
        $base = $this->copyBaseFor($original, $submittedName);
        $name = $this->copyNames->allocate($base, $this->services->namesMatching($businessId, $base));
        $slugBase = Slug::fromName($name);

        $copy = $original->duplicateAs(
            id: $this->ids->next(),
            name: $name,
            slug: $this->slugs->allocate($slugBase, $this->services->slugsMatching($businessId, $slugBase->value)),
            now: $this->clock->now(),
        );

        $this->services->save($copy);
        $this->images->copy($original->id, $copy->id);

        return $copy;
    }

    private function copyBaseFor(Service $original, ?string $submittedName): string
    {
        if ($submittedName !== null) {
            return trim($submittedName);
        }

        $room = Service::MAXIMUM_NAME_LENGTH - mb_strlen(self::COPY_SUFFIX);

        return mb_substr($original->name(), 0, $room).self::COPY_SUFFIX;
    }
}
