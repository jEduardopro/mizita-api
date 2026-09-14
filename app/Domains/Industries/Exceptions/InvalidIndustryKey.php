<?php

declare(strict_types=1);

namespace App\Domains\Industries\Exceptions;

use DomainException;

final class InvalidIndustryKey extends DomainException
{
    public static function empty(): self
    {
        return new self('An industry key cannot be empty.');
    }
}
