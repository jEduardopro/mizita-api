<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

enum CustomerSort: string
{
    case Name = 'name';

    case CreatedAt = 'created_at';
}
