<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

enum Plan: string
{
    case Free = 'free';

    case Complete = 'complete';
}
