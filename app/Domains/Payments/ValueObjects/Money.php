<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\CurrencyMismatch;
use App\Domains\Payments\Exceptions\InvalidMoneyAmount;
use App\Shared\ValueObjects\CurrencyCode;

final readonly class Money
{
    public const MAXIMUM_CENTS = 9_999_999_999;

    private const DECIMAL_SHAPE = '/^\d{1,10}(\.\d{1,2})?$/D';

    private const FRACTION_DIGITS = 2;

    private function __construct(
        public int $amount,
        public CurrencyCode $currency,
    ) {}

    /**
     * @throws InvalidMoneyAmount
     */
    public static function fromCents(int $amount, CurrencyCode $currency): self
    {
        if ($amount < 0) {
            throw InvalidMoneyAmount::negative($amount);
        }

        if ($amount > self::MAXIMUM_CENTS) {
            throw InvalidMoneyAmount::tooLarge($amount, self::MAXIMUM_CENTS);
        }

        return new self($amount, $currency);
    }

    public static function zero(CurrencyCode $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * @throws InvalidMoneyAmount
     */
    public static function fromDecimalString(string $amount, CurrencyCode $currency): self
    {
        $value = trim($amount);

        if (preg_match(self::DECIMAL_SHAPE, $value) !== 1) {
            throw InvalidMoneyAmount::malformed($amount);
        }

        [$units, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return self::fromCents((int) ($units.str_pad($fraction, self::FRACTION_DIGITS, '0')), $currency);
    }

    /**
     * @throws CurrencyMismatch
     * @throws InvalidMoneyAmount
     */
    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromCents($this->amount + $other->amount, $this->currency);
    }

    /**
     * @throws CurrencyMismatch
     * @throws InvalidMoneyAmount
     */
    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromCents($this->amount - $other->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    /**
     * @throws CurrencyMismatch
     */
    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    /**
     * @throws CurrencyMismatch
     */
    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw CurrencyMismatch::between($this->currency->value, $other->currency->value);
        }
    }
}
