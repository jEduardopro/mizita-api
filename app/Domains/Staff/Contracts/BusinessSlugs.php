<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

interface BusinessSlugs
{
    public function slugOf(string $businessId): string;
}
