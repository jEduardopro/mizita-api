<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

/**
 * A phone number exactly as the caller typed it, so that "no phone was offered"
 * and "a phone was offered and turned out not to be real" cannot be confused:
 * the first is a null PhoneNumberInput, the second is this object reaching the
 * use case and being rejected there.
 */
final readonly class PhoneNumberInput
{
    public function __construct(
        /** An ISO 3166-1 alpha-2 code as submitted; not yet known to be one we serve. */
        public string $countryCode,
        public string $nationalNumber,
    ) {}
}
