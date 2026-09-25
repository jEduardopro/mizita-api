<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Passwords;

use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Models\User;
use SensitiveParameter;

final class EncryptedTemporaryPasswordVault implements TemporaryPasswordVault
{
    private const COLUMN = 'temporary_password';

    public function keep(string $accountId, #[SensitiveParameter] string $temporaryPassword): void
    {
        $user = User::query()->where('uuid', $accountId)->first();

        if ($user === null) {
            throw AccountNotFound::withId($accountId);
        }

        $user->forceFill([self::COLUMN => $temporaryPassword])->save();
    }

    public function reveal(string $accountId): ?string
    {
        $temporaryPassword = User::query()->where('uuid', $accountId)->value(self::COLUMN);

        return is_string($temporaryPassword) ? $temporaryPassword : null;
    }

    public function discard(string $accountId): void
    {
        User::query()
            ->where('uuid', $accountId)
            ->whereNotNull(self::COLUMN)
            ->update([self::COLUMN => null]);
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function accountsHoldingTemporaryPassword(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('uuid', $accountIds)
            ->whereNotNull(self::COLUMN)
            ->pluck('uuid')
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->values()
            ->all();
    }
}
