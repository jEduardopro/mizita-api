<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\InvalidPaymentItemName;

final readonly class PaymentItemName
{
    public const MAXIMUM_LENGTH = 120;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidPaymentItemName
     */
    public static function fromString(string $value): self
    {
        $name = trim($value);

        if ($name === '') {
            throw InvalidPaymentItemName::empty();
        }

        if (mb_strlen($name) > self::MAXIMUM_LENGTH) {
            throw InvalidPaymentItemName::tooLong(self::MAXIMUM_LENGTH);
        }

        return new self($name);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }
}
