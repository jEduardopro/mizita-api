<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Domains\Appointments\Exceptions\InvalidManageToken;

final readonly class ManageToken
{
    public const BYTE_LENGTH = 32;

    private const HASH_ALGORITHM = 'sha256';

    private const HEXADECIMAL_CHARACTERS_PER_BYTE = 2;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidManageToken
     */
    public static function fromString(string $value): self
    {
        $expectedLength = self::BYTE_LENGTH * self::HEXADECIMAL_CHARACTERS_PER_BYTE;

        if (strlen($value) !== $expectedLength || ! ctype_xdigit($value)) {
            throw InvalidManageToken::malformed();
        }

        return new self($value);
    }

    public static function matches(string $candidate, string $storedHash): bool
    {
        return hash_equals($storedHash, self::digestOf($candidate));
    }

    public function hash(): string
    {
        return self::digestOf($this->value);
    }

    private static function digestOf(string $value): string
    {
        return hash(self::HASH_ALGORITHM, $value);
    }
}
