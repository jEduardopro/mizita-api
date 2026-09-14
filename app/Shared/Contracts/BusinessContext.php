<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface BusinessContext
{
    public function currentBusinessId(): string;
}
