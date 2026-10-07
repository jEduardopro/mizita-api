<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

enum NotificationScope: string
{
    case Mine = 'mine';

    case Team = 'team';
}
