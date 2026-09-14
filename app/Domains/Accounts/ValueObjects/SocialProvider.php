<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

enum SocialProvider: string
{
    case Google = 'google';
}
