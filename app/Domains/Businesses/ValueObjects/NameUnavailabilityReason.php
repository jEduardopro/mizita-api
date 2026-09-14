<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

enum NameUnavailabilityReason: string
{
    case Taken = 'taken';

    case NotSluggable = 'not_sluggable';
}
