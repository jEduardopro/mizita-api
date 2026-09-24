<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;

final readonly class JobTitle
{
    public const MAXIMUM_LENGTH = 120;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidProfileJobTitle
     */
    public static function fromNullable(?string $value): ?self
    {
        $jobTitle = trim($value ?? '');

        if ($jobTitle === '') {
            return null;
        }

        if (mb_strlen($jobTitle) > self::MAXIMUM_LENGTH) {
            throw InvalidProfileJobTitle::tooLong(self::MAXIMUM_LENGTH);
        }

        return new self($jobTitle);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }
}
