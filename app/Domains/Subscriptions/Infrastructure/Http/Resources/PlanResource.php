<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Resources;

use App\Domains\Subscriptions\Application\Dtos\PlanData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PlanData $resource
 */
final class PlanResource extends JsonResource
{
    /**
     * @return array{id: string, key: string, name: string, price_cents: int, currency_code: string, interval: string, trial_days: ?int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key->value,
            'name' => $this->resource->name,
            'price_cents' => $this->resource->priceAmount,
            'currency_code' => $this->resource->priceCurrency,
            'interval' => $this->resource->interval->value,
            'trial_days' => $this->resource->trialDays,
        ];
    }
}
