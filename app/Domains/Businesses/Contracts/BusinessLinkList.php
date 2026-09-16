<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;

interface BusinessLinkList
{
    /**
     * @return list<BusinessLinkSnapshot>
     */
    public function forBusiness(string $businessId): array;

    /**
     * @param  list<BusinessLinkSnapshot>  $links
     */
    public function replaceForBusiness(string $businessId, array $links): void;
}
