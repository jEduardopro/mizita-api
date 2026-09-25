<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\ValueObjects\RegisteredPasskey;

interface PasskeyDirectory
{
    /**
     * @return list<RegisteredPasskey>
     */
    public function forAccount(string $accountId): array;
}
