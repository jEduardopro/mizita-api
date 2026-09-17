<?php

declare(strict_types=1);

namespace App\Domains\Phones\ValueObjects;

final readonly class PhoneNumberFragment
{
    private const NON_DIGITS = '/\D+/u';

    private function __construct(
        public string $digits,
    ) {}

    public static function of(?string $value): ?self
    {
        $digits = (string) preg_replace(self::NON_DIGITS, '', $value ?? '');

        if ($digits === '') {
            return null;
        }

        return new self($digits);
    }
}
