<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Mappers;

use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;

final class PaymentMethodMapper
{
    public function toEntity(PaymentMethodModel $model): PaymentMethod
    {
        return PaymentMethod::restore(
            id: $model->uuid,
            code: $model->code,
            position: $model->position,
            active: $model->active,
            requiresIntegration: $model->requires_integration,
        );
    }
}
