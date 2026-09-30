<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

enum BookingSource: string
{
    case Admin = 'admin';
    case Public = 'public';
}
