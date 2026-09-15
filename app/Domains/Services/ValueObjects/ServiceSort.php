<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

enum ServiceSort: string
{
    case Name = 'name';

    case Price = 'price';

    case Duration = 'duration';

    case CreatedAt = 'created_at';
}
