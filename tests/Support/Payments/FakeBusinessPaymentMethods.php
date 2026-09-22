<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\BusinessPaymentMethods;

final class FakeBusinessPaymentMethods implements BusinessPaymentMethods
{
    /**
     * @var list<string>
     */
    public array $provisioned = [];

    public function __construct(
        public readonly PaymentJournal $journal = new PaymentJournal,
    ) {}

    public function enableDefaultsFor(string $businessId): void
    {
        $this->journal->record('businessPaymentMethods.enableDefaultsFor');
        $this->provisioned[] = $businessId;
    }
}
