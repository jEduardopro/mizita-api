<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class AccountAuthenticator
{
    private const NATIVE_TOKEN_NAME = 'native';

    public function startSessionFor(string $accountId): void
    {
        Auth::login($this->userOrFail($accountId), remember: true);
    }

    public function issueAccessTokenFor(string $accountId): string
    {
        return $this->userOrFail($accountId)->createToken(self::NATIVE_TOKEN_NAME)->plainTextToken;
    }

    private function userOrFail(string $accountId): User
    {
        $user = User::query()->where('uuid', $accountId)->first();

        if ($user === null) {
            throw AccountNotFound::withId($accountId);
        }

        return $user;
    }
}
