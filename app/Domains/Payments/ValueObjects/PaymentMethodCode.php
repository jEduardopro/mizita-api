<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum PaymentMethodCode: string
{
    case Cash = 'cash';

    case BankTransfer = 'bank_transfer';
}
