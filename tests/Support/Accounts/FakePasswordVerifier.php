<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\PasswordVerifier;

final class FakePasswordVerifier implements PasswordVerifier
{
    /**
     * @var array<string, string>
     */
    private array $passwords = [];

    /**
     * @var list<array{accountId: string, plaintext: string}>
     */
    public array $attempts = [];

    public function __construct(
        public readonly AccountJournal $journal = new AccountJournal,
    ) {}

    public function accepting(string $accountId, string $plaintext): self
    {
        $this->passwords[$accountId] = $plaintext;

        return $this;
    }

    public function matches(string $accountId, string $plaintext): bool
    {
        $this->journal->record('passwords.matches');
        $this->attempts[] = ['accountId' => $accountId, 'plaintext' => $plaintext];

        return ($this->passwords[$accountId] ?? null) === $plaintext;
    }
}
