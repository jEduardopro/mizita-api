<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

/**
 * Carrying the events out instead of dispatching them in place is what keeps a
 * rolled back transaction silent: the list is discarded with the rest of the
 * work. It also keeps the use case stateless.
 */
final readonly class OnboardingOutcome
{
    /**
     * The order is the contract: BusinessCreated first, then everything that
     * refers to the business it announces. Typed as objects because the tail of
     * the list belongs to another domain.
     *
     * @param  list<object>  $events  in the order they must be announced
     */
    public function __construct(
        public BusinessData $business,
        public array $events,
    ) {}
}
