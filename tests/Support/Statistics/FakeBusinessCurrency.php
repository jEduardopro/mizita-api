<?php

declare(strict_types=1);

namespace Tests\Support\Statistics;

use App\Domains\Statistics\Contracts\BusinessCurrency;
use App\Shared\ValueObjects\CurrencyCode;

final class FakeBusinessCurrency implements BusinessCurrency
{
    public const CURRENCY = 'MXN';

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function __construct(
        private readonly string $currency = self::CURRENCY,
    ) {}

    public function currencyOf(string $businessId): CurrencyCode
    {
        $this->reads[] = $businessId;

        return CurrencyCode::fromString($this->currency);
    }
}
