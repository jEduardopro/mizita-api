<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Application\Dtos\NameAvailability;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class CheckBusinessNameAvailability
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly SlugAllocator $slugs,
    ) {}

    /**
     * @return UseCaseResponse<NameAvailability>
     */
    public function handle(CheckBusinessNameAvailabilityInput $input): UseCaseResponse
    {
        try {
            $input->validate();
            $availability = $this->check($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($availability);
    }

    private function check(CheckBusinessNameAvailabilityInput $input): NameAvailability
    {
        $name = trim($input->name);
        $base = Slug::tryFromName($name);

        if ($base === null) {
            return NameAvailability::notSluggable();
        }

        if ($this->businesses->existsByName($name)) {
            return NameAvailability::taken();
        }

        $slug = $this->slugs->allocate($base, $this->businesses->slugsMatching($base->value));

        return NameAvailability::available($slug->value);
    }
}
