<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Concerns;

use App\Shared\Contracts\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query): void {
            $businessId = self::currentBusinessId();

            if ($businessId !== null) {
                $query->where($query->getModel()->qualifyColumn('business_id'), $businessId);
            }
        });

        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('business_id'))) {
                $model->setAttribute('business_id', self::currentBusinessId());
            }
        });
    }

    private static function currentBusinessId(): ?string
    {
        if (! app()->bound(BusinessContext::class)) {
            return null;
        }

        return app(BusinessContext::class)->currentBusinessId();
    }
}
