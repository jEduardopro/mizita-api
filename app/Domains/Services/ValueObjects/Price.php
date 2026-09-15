<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

use App\Domains\Services\Exceptions\InvalidServicePrice;

final readonly class Price
{
    private const SHAPE = '/^\d{1,8}(?:\.\d{1,2})?$/';

    private const SCALE = 2;

    private const SEPARATOR = '.';

    private const ZERO = '0';

    private function __construct(
        public string $amount,
    ) {}

    /**
     * @throws InvalidServicePrice
     */
    public static function fromString(string $value): self
    {
        $amount = trim($value);

        if (str_starts_with($amount, '-')) {
            throw InvalidServicePrice::negative();
        }

        if (preg_match(self::SHAPE, $amount) !== 1) {
            throw InvalidServicePrice::malformed();
        }

        return new self(self::normalize($amount));
    }

    public static function restore(string $amount): self
    {
        return new self($amount);
    }

    public static function free(): self
    {
        return new self(self::ZERO.self::SEPARATOR.str_repeat(self::ZERO, self::SCALE));
    }

    public function isFree(): bool
    {
        return $this->equals(self::free());
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    private static function normalize(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode(self::SEPARATOR, $amount, 2), 2, '');

        $whole = ltrim($whole, self::ZERO);

        return ($whole === '' ? self::ZERO : $whole)
            .self::SEPARATOR
            .str_pad($fraction, self::SCALE, self::ZERO);
    }
}
