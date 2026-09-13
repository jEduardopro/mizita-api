<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Application\Dtos\NameAvailability;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Domains\Businesses\ValueObjects\Slug;

/**
 * Answers whether a business name can be used, and what address it would get.
 *
 * It never throws. The signup form asks this on every keystroke and a "no" is
 * the expected answer half the time, so refusal is a value here rather than an
 * exception - which is exactly why Slug has a nullable twin.
 *
 * The answer is advisory. Two people can be told the same free name at the same
 * moment; only the partial unique index settles it, and OnboardBusiness checks
 * again on the way in.
 *
 * Depends only on interfaces, so it can be built with mocks and exercised
 * without a database.
 */
final class CheckBusinessNameAvailability
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly SlugAllocator $slugs,
    ) {}

    public function handle(CheckBusinessNameAvailabilityInput $input): NameAvailability
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
