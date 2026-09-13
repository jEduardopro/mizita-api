<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

/**
 * What one onboarding transaction produced: the business to answer with, and
 * the events it earned the right to announce.
 *
 * Carrying the events out instead of dispatching them in place is what keeps a
 * rolled back transaction silent - the list is simply discarded with the rest
 * of the work. Returning them also keeps the use case stateless, where
 * accumulating into a property would leak between calls to handle().
 *
 * Internal to the application layer: it never crosses the HTTP boundary.
 */
final readonly class OnboardingOutcome
{
    /**
     * The list is ordered, and the order is the contract: BusinessCreated
     * first, then everything that refers to the business it announces.
     *
     * Typed as objects because the tail of the list belongs to another domain
     * and reaches here through a port that deliberately keeps it opaque.
     *
     * @param  list<object>  $events  in the order they must be announced
     */
    public function __construct(
        public BusinessData $business,
        public array $events,
    ) {}
}
