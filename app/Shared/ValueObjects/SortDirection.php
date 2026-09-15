<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

enum SortDirection: string
{
    case Ascending = 'asc';

    case Descending = 'desc';
}
