<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use App\Domains\Businesses\Exceptions\InvalidBusinessAbout;

final readonly class About
{
    public const MAXIMUM_LENGTH = 2000;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidBusinessAbout
     */
    public static function fromString(string $value): self
    {
        $about = trim($value);

        if ($about === '') {
            throw InvalidBusinessAbout::empty();
        }

        if (mb_strlen($about) > self::MAXIMUM_LENGTH) {
            throw InvalidBusinessAbout::tooLong(self::MAXIMUM_LENGTH);
        }

        return new self($about);
    }

    /**
     * @throws InvalidBusinessAbout
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
}
