<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

use App\Domains\Customers\Exceptions\InvalidCustomerEmail;

final readonly class CustomerEmail
{
    public const MAXIMUM_LENGTH = 254;

    private const SEPARATOR = '@';

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidCustomerEmail
     */
    public static function fromString(string $value): self
    {
        $email = trim($value);

        if ($email === '') {
            throw InvalidCustomerEmail::empty();
        }

        if (mb_strlen($email) > self::MAXIMUM_LENGTH) {
            throw InvalidCustomerEmail::tooLong();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidCustomerEmail::malformed();
        }

        return new self(self::withLowercaseDomain($email));
    }

    /**
     * @throws InvalidCustomerEmail
     */
    public static function fromNullable(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::fromString($value);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function withLowercaseDomain(string $email): string
    {
        $parts = explode(self::SEPARATOR, $email);
        $domain = mb_strtolower((string) array_pop($parts));

        return implode(self::SEPARATOR, $parts).self::SEPARATOR.$domain;
    }
}
