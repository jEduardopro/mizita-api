<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

final class AccountAuthenticator
{
    private const NATIVE_TOKEN_NAME = 'native';

    private const CHALLENGED_USER_SESSION_KEY = 'login.id';

    private const REMEMBER_SESSION_KEY = 'login.remember';

    private const REMEMBER_AFTER_CHALLENGE = true;

    public function __construct(
        private readonly TemporaryPasswordVault $vault,
    ) {}

    public function startSessionFor(string $accountId): void
    {
        Auth::login($this->userOrFail($accountId), remember: true);
    }

    public function challengeSecondFactorFor(string $accountId): void
    {
        Session::put([
            self::CHALLENGED_USER_SESSION_KEY => $this->userOrFail($accountId)->getKey(),
            self::REMEMBER_SESSION_KEY => self::REMEMBER_AFTER_CHALLENGE,
        ]);
    }

    public function issueAccessTokenFor(string $accountId): string
    {
        $accessToken = $this->userOrFail($accountId)->createToken(self::NATIVE_TOKEN_NAME)->plainTextToken;

        $this->vault->discard($accountId);

        return $accessToken;
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
