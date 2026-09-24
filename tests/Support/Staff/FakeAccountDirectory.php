<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use Throwable;

final class FakeAccountDirectory implements AccountDirectory
{
    /**
     * @var array<string, AccountSnapshot>
     */
    private array $accounts = [];

    /**
     * @var list<list<string>>
     */
    public array $calls = [];

    /**
     * @var list<array{accountId: string, name: string}>
     */
    public array $renames = [];

    private ?Throwable $renameRefusal = null;

    private StaffJournal $journal;

    public function __construct(AccountSnapshot ...$accounts)
    {
        foreach ($accounts as $account) {
            $this->accounts[$account->id] = $account;
        }

        $this->journal = new StaffJournal;
    }

    public function recordingInto(StaffJournal $journal): self
    {
        $this->journal = $journal;

        return $this;
    }

    public function refuseRenameWith(Throwable $refusal): self
    {
        $this->renameRefusal = $refusal;

        return $this;
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<AccountSnapshot>
     */
    public function describe(array $accountIds): array
    {
        $this->calls[] = array_values($accountIds);

        $snapshots = [];

        foreach ($accountIds as $accountId) {
            if (isset($this->accounts[$accountId])) {
                $snapshots[] = $this->accounts[$accountId];
            }
        }

        return $snapshots;
    }

    public function rename(string $accountId, string $name): void
    {
        $this->journal->record('accounts.rename');

        if ($this->renameRefusal !== null) {
            throw $this->renameRefusal;
        }

        $account = $this->accounts[$accountId] ?? throw StaffMemberNotFound::forAccount($accountId);

        $this->renames[] = ['accountId' => $accountId, 'name' => $name];
        $this->accounts[$accountId] = new AccountSnapshot($account->id, trim($name), $account->email, $account->hasPassword);
    }

    public function callCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<string>
     */
    public function lastCall(): array
    {
        return $this->calls[count($this->calls) - 1];
    }
}
