<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent\Mappers;

use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\ValueObjects\AvailablePaymentMethod;

final class AvailablePaymentMethodMapper
{
    private const ENABLED_COLUMN = 'enabled';

    public function toAvailable(PaymentMethodModel $model): AvailablePaymentMethod
    {
        return new AvailablePaymentMethod(
            id: $model->uuid,
            code: $model->code,
            position: $model->position,
            enabled: (bool) $model->getAttribute(self::ENABLED_COLUMN),
            requiresIntegration: $model->requires_integration,
        );
    }
}
