<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\CreateBusinessInput;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Events\BusinessCreated;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Depends only on interfaces, so it can be built with mocks and exercised
 * without a database.
 */
final class CreateBusiness
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly Dispatcher $events,
    ) {}

    public function handle(CreateBusinessInput $input): BusinessData
    {
        if ($this->businesses->existsBySlug($input->slug)) {
            throw BusinessSlugAlreadyTaken::for($input->slug);
        }

        $business = Business::create(
            id: $this->ids->next(),
            name: $input->name,
            slug: $input->slug,
            now: $this->clock->now(),
        );

        $this->businesses->save($business);
        $this->events->dispatch(new BusinessCreated($business->id));

        return BusinessData::fromEntity($business);
    }
}
