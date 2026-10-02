<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\ValueObjects\AccountSharing;

interface TeamAccountSharing
{
    public function sharingOf(string $accountId, string $businessId): AccountSharing;

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function sharedAmong(array $accountIds, string $businessId): array;
}
