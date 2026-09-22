<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Exceptions\PaymentMethodNotEnabled;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\ValueObjects\AvailablePaymentMethod;

final class FakePaymentMethodCatalog implements PaymentMethodCatalog
{
    /**
     * @var array<string, PaymentMethod>
     */
    private array $methods = [];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $enablementByBusiness = [];

    /**
     * @var list<array{businessId: string, paymentMethodId: string}>
     */
    public array $reads = [];

    /**
     * @var list<list<string>>
     */
    public array $batchReads = [];

    /**
     * @var list<string>
     */
    public array $availabilityReads = [];

    public function __construct(
        public readonly PaymentJournal $journal = new PaymentJournal,
    ) {}

    public function register(PaymentMethod ...$methods): self
    {
        foreach ($methods as $method) {
            $this->methods[$method->id] = $method;
        }

        return $this;
    }

    public function enableFor(string $businessId, PaymentMethod ...$methods): self
    {
        foreach ($methods as $method) {
            $this->register($method);
            $this->enablementByBusiness[$businessId][$method->id] = true;
        }

        return $this;
    }

    public function disableFor(string $businessId, PaymentMethod ...$methods): self
    {
        foreach ($methods as $method) {
            $this->register($method);
            $this->enablementByBusiness[$businessId][$method->id] = false;
        }

        return $this;
    }

    /**
     * @return list<AvailablePaymentMethod>
     */
    public function availableFor(string $businessId): array
    {
        $this->journal->record('paymentMethods.availableFor');
        $this->availabilityReads[] = $businessId;

        $active = array_values(array_filter(
            $this->methods,
            static fn (PaymentMethod $method): bool => $method->active,
        ));

        usort(
            $active,
            static fn (PaymentMethod $left, PaymentMethod $right): int => $left->position <=> $right->position,
        );

        return array_map(
            fn (PaymentMethod $method): AvailablePaymentMethod => new AvailablePaymentMethod(
                id: $method->id,
                code: $method->code,
                position: $method->position,
                enabled: $this->enablementByBusiness[$businessId][$method->id] ?? false,
                requiresIntegration: $method->requiresIntegration,
            ),
            $active,
        );
    }

    public function findEnabledFor(string $businessId, string $paymentMethodId): PaymentMethod
    {
        $this->journal->record('paymentMethods.findEnabledFor');
        $this->reads[] = ['businessId' => $businessId, 'paymentMethodId' => $paymentMethodId];

        $method = $this->methods[$paymentMethodId] ?? null;

        if ($method === null || ! $method->active) {
            throw PaymentMethodNotFound::withId($paymentMethodId);
        }

        if (($this->enablementByBusiness[$businessId][$paymentMethodId] ?? false) !== true) {
            throw PaymentMethodNotEnabled::withId($paymentMethodId);
        }

        return $method;
    }

    /**
     * @param  list<string>  $paymentMethodIds
     * @return array<string, PaymentMethod>
     */
    public function describeMany(array $paymentMethodIds): array
    {
        $this->journal->record('paymentMethods.describeMany');
        $this->batchReads[] = array_values($paymentMethodIds);

        $described = [];

        foreach ($paymentMethodIds as $paymentMethodId) {
            if (isset($this->methods[$paymentMethodId])) {
                $described[$paymentMethodId] = $this->methods[$paymentMethodId];
            }
        }

        return $described;
    }
}
