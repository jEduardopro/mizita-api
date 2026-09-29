<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent;

use App\Domains\Subscriptions\Contracts\PlanCatalog;
use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPlan;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers\PlanMapper;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\PlanModel;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;

final class EloquentPlanCatalog implements PlanCatalog
{
    public function __construct(
        private readonly PlanMapper $mapper,
    ) {}

    public function findById(string $id): PlanOffer
    {
        $model = PlanModel::query()->where('uuid', $id)->first();

        if ($model === null) {
            throw InvalidSubscriptionPlan::unknown($id);
        }

        return $this->mapper->toOffer($model);
    }

    /**
     * @return list<PlanOffer>
     */
    public function all(): array
    {
        $models = PlanModel::query()
            ->orderBy('price_amount')
            ->orderBy('id')
            ->get();

        return array_values(array_map(
            fn (PlanModel $model): PlanOffer => $this->mapper->toOffer($model),
            $models->all(),
        ));
    }
}
