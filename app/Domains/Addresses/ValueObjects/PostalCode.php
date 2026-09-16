<?php

declare(strict_types=1);

namespace App\Domains\Addresses\ValueObjects;

use App\Domains\Addresses\Exceptions\InvalidAddressPostalCode;

final readonly class PostalCode
{
    public const MINIMUM_DIGITS = 4;

    public const MAXIMUM_DIGITS = 10;

    private const SHAPE = '/^\d{'.self::MINIMUM_DIGITS.','.self::MAXIMUM_DIGITS.'}$/D';

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidAddressPostalCode
     */
    public static function fromString(string $value): self
    {
        $candidate = trim($value);

        if ($candidate === '') {
            throw InvalidAddressPostalCode::empty();
        }

        if (preg_match(self::SHAPE, $candidate) !== 1) {
            throw InvalidAddressPostalCode::malformed();
        }

        return new self($candidate);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
