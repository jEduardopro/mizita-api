<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\TeamTemporaryPasswords;
use Throwable;

final class FakeTeamTemporaryPasswords implements TeamTemporaryPasswords
{
    /**
     * @var array<string, string>
     */
    private array $temporaryPasswords = [];

    private ?Throwable $revealFailure = null;

    /**
     * @var list<string>
     */
    public array $reveals = [];

    /**
     * @var list<list<string>>
     */
    public array $batchReads = [];

    public function holds(string $accountId, string $temporaryPassword): self
    {
        $this->temporaryPasswords[$accountId] = $temporaryPassword;

        return $this;
    }

    public function failRevealWith(Throwable $failure): self
    {
        $this->revealFailure = $failure;

        return $this;
    }

    public function revealFor(string $accountId): ?string
    {
        $this->reveals[] = $accountId;

        if ($this->revealFailure !== null) {
            throw $this->revealFailure;
        }

        return $this->temporaryPasswords[$accountId] ?? null;
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function availableAmong(array $accountIds): array
    {
        $this->batchReads[] = array_values($accountIds);

        return array_values(array_filter(
            $accountIds,
            fn (string $accountId): bool => isset($this->temporaryPasswords[$accountId]),
        ));
    }
}
