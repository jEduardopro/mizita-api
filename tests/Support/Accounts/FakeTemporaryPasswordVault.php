<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use SensitiveParameter;

final class FakeTemporaryPasswordVault implements TemporaryPasswordVault
{
    /**
     * @var array<string, string>
     */
    private array $temporaryPasswords = [];

    /**
     * @var list<array{accountId: string, temporaryPassword: string}>
     */
    public array $kept = [];

    /**
     * @var list<string>
     */
    public array $discarded = [];

    /**
     * @var list<list<string>>
     */
    public array $batchReads = [];

    public function holds(string $accountId, string $temporaryPassword): self
    {
        $this->temporaryPasswords[$accountId] = $temporaryPassword;

        return $this;
    }

    public function keep(string $accountId, #[SensitiveParameter] string $temporaryPassword): void
    {
        $this->kept[] = ['accountId' => $accountId, 'temporaryPassword' => $temporaryPassword];
        $this->temporaryPasswords[$accountId] = $temporaryPassword;
    }

    public function reveal(string $accountId): ?string
    {
        return $this->temporaryPasswords[$accountId] ?? null;
    }

    public function discard(string $accountId): void
    {
        $this->discarded[] = $accountId;
        unset($this->temporaryPasswords[$accountId]);
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function accountsHoldingTemporaryPassword(array $accountIds): array
    {
        $this->batchReads[] = array_values($accountIds);

        return array_values(array_filter(
            $accountIds,
            fn (string $accountId): bool => isset($this->temporaryPasswords[$accountId]),
        ));
    }
}
