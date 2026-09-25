<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

enum CalendarProvider: string
{
    case Google = 'google';
}
