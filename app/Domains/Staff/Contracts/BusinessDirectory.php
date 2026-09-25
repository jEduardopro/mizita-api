<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

interface BusinessDirectory
{
    public function nameOf(string $businessId): string;
}
