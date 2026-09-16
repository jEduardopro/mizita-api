<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\InvalidBusinessCurrency;

final readonly class CurrencyCode
{
    private const DEFAULT = 'MXN';

    private const SHAPE = '/^[A-Z]{3}$/';

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidBusinessCurrency
     */
    public static function fromString(string $value): self
    {
        $code = mb_strtoupper(trim($value));

        if (preg_match(self::SHAPE, $code) !== 1) {
            throw InvalidBusinessCurrency::forValue($value);
        }

        return new self($code);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
