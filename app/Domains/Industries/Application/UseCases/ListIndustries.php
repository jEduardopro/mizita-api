<?php

declare(strict_types=1);

namespace App\Domains\Industries\Application\UseCases;

use App\Domains\Industries\Application\Dtos\IndustryData;
use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Entities\Industry;
use App\Shared\Application\UseCaseResponse;

final class ListIndustries
{
    public function __construct(
        private readonly IndustryRepository $industries,
    ) {}

    /**
     * @return UseCaseResponse<list<IndustryData>>
     */
    public function handle(): UseCaseResponse
    {
        return UseCaseResponse::success(array_map(
            static fn (Industry $industry): IndustryData => IndustryData::fromEntity($industry),
            $this->industries->allActive(),
        ));
    }
}
