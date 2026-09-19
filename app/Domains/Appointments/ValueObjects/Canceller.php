<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

enum Canceller: string
{
    case Customer = 'customer';

    case Business = 'business';
}
