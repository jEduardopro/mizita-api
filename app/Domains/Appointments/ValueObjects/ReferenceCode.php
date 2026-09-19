<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Domains\Appointments\Exceptions\InvalidReferenceCode;

final readonly class ReferenceCode
{
    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const LENGTH = 8;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidReferenceCode
     */
    public static function fromString(string $value): self
    {
        $code = strtoupper(trim($value));

        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            throw InvalidReferenceCode::malformed();
        }

        return new self($code);
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
