<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Contracts\PasskeyDirectory;
use App\Domains\Accounts\ValueObjects\RegisteredPasskey;

final class FakePasskeyDirectory implements PasskeyDirectory
{
    /** @var array<string, list<RegisteredPasskey>> */
    private array $passkeysByAccount = [];

    /** @var list<string> */
    public array $lookups = [];

    public function register(string $accountId, RegisteredPasskey ...$passkeys): void
    {
        $this->passkeysByAccount[$accountId] = [
            ...($this->passkeysByAccount[$accountId] ?? []),
            ...array_values($passkeys),
        ];
    }

    public function forAccount(string $accountId): array
    {
        $this->lookups[] = $accountId;

        return $this->passkeysByAccount[$accountId] ?? [];
    }
}
