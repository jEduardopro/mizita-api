<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\ValueObjects\ClosureRetention;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;

final class FindBusinessesDueForPurge
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<list<string>>
     */
    public function handle(): UseCaseResponse
    {
        return UseCaseResponse::success(
            $this->businesses->idsDueForPurge(ClosureRetention::closedNoLaterThanFor($this->clock->now())),
        );
    }
}
