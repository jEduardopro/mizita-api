<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

enum AuthorizationScope: string
{
    case Platform = 'platform';

    case Business = 'business';
}
