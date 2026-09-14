<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;

interface PhoneNumberParser
{
    public function parse(CountryCode $country, string $nationalNumber): ?PhoneNumber;
}
