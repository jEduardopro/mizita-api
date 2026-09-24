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

    public function findByEmail(string $email): ?Account;

    /**
     * @param  list<string>  $ids
     * @return list<Account>
     */
    public function findManyByIds(array $ids): array;

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function idsHoldingPassword(array $ids): array;

    /**
     * @throws AccountAlreadyRegistered
     */
    public function save(Account $account): void;
}
