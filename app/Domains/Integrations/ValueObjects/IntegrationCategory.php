<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

enum IntegrationCategory: string
{
    case CalendarSync = 'calendar_sync';
}
