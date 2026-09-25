<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use App\Models\User;
use App\Shared\Contracts\AccountSessions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DatabaseAccountSessions implements AccountSessions
{
    private const DATABASE_SESSION_DRIVER = 'database';

    private const REMEMBER_TOKEN_LENGTH = 60;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Repository $config,
    ) {}

    /**
     * @param  list<string>  $accountIds
     */
    public function endAll(array $accountIds): void
    {
        $users = $this->usersWithIds($accountIds);

        if ($users->isEmpty()) {
            return;
        }

        $users->each(function (User $user): void {
            $user->tokens()->delete();
            $this->rotateRememberTokenOf($user);
        });

        $this->sessionsOf($users->map(static fn (User $user): int => (int) $user->getKey())->all())?->delete();
    }

    public function endAllExcept(string $accountId, string $keptSessionId): void
    {
        $user = $this->usersWithIds([$accountId])->first();

        if (! $user instanceof User) {
            return;
        }

        $user->tokens()->delete();

        $this->sessionsOf([(int) $user->getKey()])?->where('id', '!=', $keptSessionId)->delete();
    }

    /**
     * @param  list<string>  $accountIds
     * @return Collection<int, User>
     */
    private function usersWithIds(array $accountIds): Collection
    {
        if ($accountIds === []) {
            return new Collection;
        }

        return User::withTrashed()->whereIn('uuid', $accountIds)->get()->toBase();
    }

    private function rotateRememberTokenOf(User $user): void
    {
        User::withTrashed()
            ->whereKey($user->getKey())
            ->toBase()
            ->update(['remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH)]);
    }

    /**
     * @param  list<int>  $userKeys
     */
    private function sessionsOf(array $userKeys): ?Builder
    {
        if ($this->config->get('session.driver') !== self::DATABASE_SESSION_DRIVER) {
            return null;
        }

        return $this->database
            ->connection($this->config->get('session.connection'))
            ->table((string) $this->config->get('session.table'))
            ->whereIn('user_id', $userKeys);
    }
}
