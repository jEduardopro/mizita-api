<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\Exceptions\AccountNotFound;

/**
 * Port for Account persistence. It speaks entities, never Eloquent models or
 * query builders, so use cases stay independent of the database.
 */
interface AccountRepository
{
    /**
     * @throws AccountNotFound
     */
    public function findById(string $id): Account;

    /**
     * Null means "no account uses this address", which is a legitimate answer
     * a caller branches on - not a failure. Matching is case insensitive.
     */
    public function findByEmail(string $email): ?Account;

    /**
     * @throws AccountAlreadyRegistered when a concurrent writer claimed the
     *                                  address between the caller's lookup and this write
     */
    public function save(Account $account): void;
}
