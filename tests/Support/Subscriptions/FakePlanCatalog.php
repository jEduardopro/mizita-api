<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\PlanCatalog;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPlan;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;

final class FakePlanCatalog implements PlanCatalog
{
    /** @var list<PlanOffer> */
    private array $offers;

    /** @var list<string> */
    public array $lookups = [];

    public function __construct(PlanOffer ...$offers)
    {
        $this->offers = array_values($offers);
    }

    public function findById(string $id): PlanOffer
    {
        $this->lookups[] = $id;

        foreach ($this->offers as $offer) {
            if ($offer->id === $id) {
                return $offer;
            }
        }

        throw InvalidSubscriptionPlan::unknown($id);
    }

    public function all(): array
    {
        return $this->offers;
    }
}
