<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\Exceptions\AccountNotFound;

interface AccountRepository
{
    /**
     * @throws AccountNotFound
     */
    public function findById(string $id): Account;

    /** Null means no account uses this address, which callers branch on. Matching is case insensitive. */
    public function findByEmail(string $email): ?Account;

    /**
     * @throws AccountAlreadyRegistered when a concurrent writer claimed the
     *                                  address between the caller's lookup and this write
     */
    public function save(Account $account): void;
}
