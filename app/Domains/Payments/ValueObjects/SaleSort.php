<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum SaleSort: string
{
    case CreatedAt = 'created_at';

    case Total = 'total';
}
