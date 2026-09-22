<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\BusinessProfile;
use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use App\Shared\ValueObjects\CurrencyCode;

final class FakeBusinessProfile implements BusinessProfile
{
    /**
     * @var array<string, CurrencyCode>
     */
    private array $currencies = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function __construct(
        public readonly PaymentJournal $journal = new PaymentJournal,
    ) {}

    public function add(string $businessId, CurrencyCode $currency): self
    {
        $this->currencies[$businessId] = $currency;

        return $this;
    }

    public function currencyFor(string $businessId): CurrencyCode
    {
        $this->journal->record('businesses.currencyFor');
        $this->reads[] = $businessId;

        return $this->currencies[$businessId] ?? throw PaymentBusinessNotFound::withId($businessId);
    }
}
