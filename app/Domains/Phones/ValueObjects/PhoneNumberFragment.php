<?php

declare(strict_types=1);

namespace App\Domains\Phones\ValueObjects;

final readonly class PhoneNumberFragment
{
    public const MINIMUM_DIGITS = 4;

    private const NON_DIGITS = '/\D+/u';

    private function __construct(
        public string $digits,
    ) {}

    public static function of(?string $value): ?self
    {
        $digits = (string) preg_replace(self::NON_DIGITS, '', $value ?? '');

        if (strlen($digits) < self::MINIMUM_DIGITS) {
            return null;
        }

        return new self($digits);
    }
}
