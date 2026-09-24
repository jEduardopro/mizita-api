<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\AccountSnapshot;

interface AccountDirectory
{
    /**
     * @param  list<string>  $accountIds
     * @return list<AccountSnapshot>
     */
    public function describe(array $accountIds): array;

    /**
     * @throws StaffMemberNotFound
     * @throws InvalidProfileName
     */
    public function rename(string $accountId, string $name): void;
}
