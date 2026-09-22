<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent;

use App\Domains\Payments\Contracts\BusinessPaymentMethods;
use App\Domains\Payments\Infrastructure\Eloquent\Models\BusinessPaymentMethodModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Shared\Contracts\BusinessTeamKey;

final class EloquentBusinessPaymentMethods implements BusinessPaymentMethods
{
    public function __construct(
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function enableDefaultsFor(string $businessId): void
    {
        $businessKey = $this->businessKeys->teamKeyFor($businessId);

        foreach (self::defaults() as $method) {
            BusinessPaymentMethodModel::query()->firstOrCreate(
                [
                    'business_id' => $businessKey,
                    'payment_method_id' => $method->id,
                ],
                [
                    'enabled' => true,
                    'position' => $method->position,
                ],
            );
        }
    }

    /**
     * @return iterable<PaymentMethodModel>
     */
    private static function defaults(): iterable
    {
        return PaymentMethodModel::query()
            ->where('active', true)
            ->whereIn('code', self::defaultCodes())
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private static function defaultCodes(): array
    {
        return [
            PaymentMethodCode::Cash->value,
            PaymentMethodCode::BankTransfer->value,
        ];
    }
}
