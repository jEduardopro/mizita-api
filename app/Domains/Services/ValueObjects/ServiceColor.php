<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

enum ServiceColor: string
{
    case Red = 'red';

    case Orange = 'orange';

    case Amber = 'amber';

    case Purple = 'purple';

    case Blue = 'blue';

    case Sand = 'sand';

    case Slate = 'slate';

    case Teal = 'teal';

    case Green = 'green';
}
