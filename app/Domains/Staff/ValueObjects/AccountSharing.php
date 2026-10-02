<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

enum AccountSharing
{
    case ExclusiveToBusiness;

    case SharedWithOtherBusinesses;
}
