<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPrice;
use App\Shared\ValueObjects\CurrencyCode;

final readonly class SubscriptionPrice
{
    private function __construct(
        public int $amountInMinorUnits,
        public CurrencyCode $currency,
    ) {}

    /**
     * @throws InvalidSubscriptionPrice
     */
    public static function of(int $amountInMinorUnits, CurrencyCode $currency): self
    {
        if ($amountInMinorUnits < 0) {
            throw InvalidSubscriptionPrice::negative($amountInMinorUnits);
        }

        return new self($amountInMinorUnits, $currency);
    }

    public static function restore(int $amountInMinorUnits, CurrencyCode $currency): self
    {
        return new self($amountInMinorUnits, $currency);
    }

    /**
     * @throws InvalidSubscriptionPrice
     */
    public function withAmount(int $amountInMinorUnits): self
    {
        return self::of($amountInMinorUnits, $this->currency);
    }
}
