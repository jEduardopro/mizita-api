<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent;

use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Exceptions\PaymentMethodNotEnabled;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Infrastructure\Eloquent\Mappers\AvailablePaymentMethodMapper;
use App\Domains\Payments\Infrastructure\Eloquent\Mappers\PaymentMethodMapper;
use App\Domains\Payments\Infrastructure\Eloquent\Models\BusinessPaymentMethodModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentMethodModel;
use App\Domains\Payments\ValueObjects\AvailablePaymentMethod;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Query\JoinClause;

final class EloquentPaymentMethodCatalog implements PaymentMethodCatalog
{
    private const ENABLEMENT_TABLE = 'business_payment_methods';

    /**
     * @var list<string>
     */
    private const AVAILABILITY_COLUMNS = [
        'payment_methods.uuid',
        'payment_methods.code',
        'payment_methods.position',
        'payment_methods.requires_integration',
        'business_payment_methods.enabled',
    ];

    public function __construct(
        private readonly PaymentMethodMapper $mapper,
        private readonly AvailablePaymentMethodMapper $availabilityMapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    /**
     * @return list<AvailablePaymentMethod>
     */
    public function availableFor(string $businessId): array
    {
        $businessKey = $this->businessKeys->teamKeyFor($businessId);

        return PaymentMethodModel::query()
            ->leftJoin(
                self::ENABLEMENT_TABLE,
                static fn (JoinClause $join) => $join
                    ->on('business_payment_methods.payment_method_id', '=', 'payment_methods.id')
                    ->where('business_payment_methods.business_id', $businessKey)
                    ->whereNull('business_payment_methods.deleted_at'),
            )
            ->where('payment_methods.active', true)
            ->orderBy('payment_methods.position')
            ->orderBy('payment_methods.id')
            ->get(self::AVAILABILITY_COLUMNS)
            ->map(fn (PaymentMethodModel $model): AvailablePaymentMethod => $this->availabilityMapper->toAvailable($model))
            ->values()
            ->all();
    }

    public function findEnabledFor(string $businessId, string $paymentMethodId): PaymentMethod
    {
        $model = PaymentMethodModel::query()
            ->where('uuid', $paymentMethodId)
            ->where('active', true)
            ->first();

        if ($model === null) {
            throw PaymentMethodNotFound::withId($paymentMethodId);
        }

        if (! $this->isEnabled($businessId, (int) $model->id)) {
            throw PaymentMethodNotEnabled::withId($paymentMethodId);
        }

        return $this->mapper->toEntity($model);
    }

    /**
     * @param  list<string>  $paymentMethodIds
     * @return array<string, PaymentMethod>
     */
    public function describeMany(array $paymentMethodIds): array
    {
        if ($paymentMethodIds === []) {
            return [];
        }

        $described = [];

        $models = PaymentMethodModel::query()
            ->withTrashed()
            ->whereIn('uuid', array_values(array_unique($paymentMethodIds)))
            ->get();

        foreach ($models as $model) {
            $described[$model->uuid] = $this->mapper->toEntity($model);
        }

        return $described;
    }

    private function isEnabled(string $businessId, int $paymentMethodKey): bool
    {
        return BusinessPaymentMethodModel::query()
            ->where('business_id', $this->businessKeys->teamKeyFor($businessId))
            ->where('payment_method_id', $paymentMethodKey)
            ->where('enabled', true)
            ->exists();
    }
}
