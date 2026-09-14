<?php

declare(strict_types=1);

namespace App\Domains\Industries\Application\UseCases;

use App\Domains\Industries\Application\Dtos\IndustryData;
use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Entities\Industry;

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
