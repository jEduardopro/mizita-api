<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum TransactionSort: string
{
    case ProcessedAt = 'processed_at';

    case Amount = 'amount';
}
