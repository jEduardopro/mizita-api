<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\ValueObjects\TemporaryPassword;

interface TemporaryPasswordGenerator
{
    public function generate(): TemporaryPassword;
}
