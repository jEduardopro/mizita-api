<?php

declare(strict_types=1);

namespace App\Domains\Industries\Application\UseCases;

use App\Domains\Industries\Application\Dtos\IndustryData;
use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Entities\Industry;

/**
 * Lists the industries a business may choose from.
 *
 * No input DTO: the query takes no arguments, and the catalog is the same for
 * every caller because it belongs to the platform, not to a tenant.
 */
final class ListIndustries
{
    public function __construct(
        private readonly IndustryRepository $industries,
    ) {}

    /**
     * @return list<IndustryData>
     */
    public function handle(): array
    {
        return array_map(
            static fn (Industry $industry): IndustryData => IndustryData::fromEntity($industry),
            $this->industries->allActive(),
        );
    }
}
