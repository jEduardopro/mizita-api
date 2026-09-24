<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use App\Domains\Staff\Exceptions\InvalidProfileAbout;

final readonly class About
{
    public const MAXIMUM_LENGTH = 1000;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidProfileAbout
     */
    public static function fromNullable(?string $value): ?self
    {
        $about = trim($value ?? '');

        if ($about === '') {
            return null;
        }

        if (mb_strlen($about) > self::MAXIMUM_LENGTH) {
            throw InvalidProfileAbout::tooLong(self::MAXIMUM_LENGTH);
        }

        return new self($about);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }
}
