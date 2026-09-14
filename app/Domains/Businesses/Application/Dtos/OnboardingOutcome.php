<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

final readonly class OnboardingOutcome
{
    /**
     * @param  list<object>  $events
     */
    public function __construct(
        public BusinessData $business,
        public array $events,
    ) {}
}
