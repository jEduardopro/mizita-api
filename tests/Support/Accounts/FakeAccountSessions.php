<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Shared\Contracts\AccountSessions;
use Tests\Support\FakeTransactionManager;

final class FakeAccountSessions implements AccountSessions
{
    /**
     * @var list<list<string>>
     */
    public array $endedForAll = [];

    /**
     * @var list<array{accountId: string, keptSessionId: string}>
     */
    public array $endedExcept = [];

    /**
     * @var list<bool>
     */
    public array $endedInsideTransaction = [];

    public function __construct(
        public readonly AccountJournal $journal = new AccountJournal,
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function endAll(array $accountIds): void
    {
        $this->journal->record('sessions.endAll');
        $this->endedForAll[] = $accountIds;
        $this->endedInsideTransaction[] = $this->transactions?->isRunning() ?? false;
    }

    public function endAllExcept(string $accountId, string $keptSessionId): void
    {
        $this->journal->record('sessions.endAllExcept');
        $this->endedExcept[] = ['accountId' => $accountId, 'keptSessionId' => $keptSessionId];
        $this->endedInsideTransaction[] = $this->transactions?->isRunning() ?? false;
    }
}
