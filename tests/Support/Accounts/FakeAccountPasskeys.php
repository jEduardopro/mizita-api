<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\AccountPasskeys;
use Tests\Support\FakeTransactionManager;

final class FakeAccountPasskeys implements AccountPasskeys
{
    /**
     * @var list<string>
     */
    public array $deletedFor = [];

    /**
     * @var list<bool>
     */
    public array $deletedInsideTransaction = [];

    public function __construct(
        public readonly AccountJournal $journal = new AccountJournal,
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function deleteAllOf(string $accountId): void
    {
        $this->journal->record('passkeys.deleteAllOf');
        $this->deletedFor[] = $accountId;
        $this->deletedInsideTransaction[] = $this->transactions?->isRunning() ?? false;
    }
}
