<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

/**
 * The identity providers an account can be linked to. A backed enum rather
 * than a string constant, so an unknown provider cannot reach the database.
 */
enum SocialProvider: string
{
    case Google = 'google';
}
