<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\InvalidBusinessContactEmail;

final readonly class ContactEmail
{
    public const MAXIMUM_LENGTH = 255;

    private const SEPARATOR = '@';

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidBusinessContactEmail
     */
    public static function fromString(string $value): self
    {
        $email = trim($value);

        if ($email === '') {
            throw InvalidBusinessContactEmail::empty();
        }

        if (mb_strlen($email) > self::MAXIMUM_LENGTH) {
            throw InvalidBusinessContactEmail::tooLong(self::MAXIMUM_LENGTH);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidBusinessContactEmail::malformed();
        }

        return new self(self::withLowercaseDomain($email));
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
