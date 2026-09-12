<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Issues the credential an authenticated account signs in with.
 *
 * The use cases deal in account uuids; the guard and Sanctum deal in the
 * authenticatable. Resolving one into the other is infrastructure work, so it
 * happens here instead of leaking a query into a controller.
 */
final class AccountAuthenticator
{
    /**
     * Names the token a native client receives, so it is revocable on its own.
     */
    private const NATIVE_TOKEN_NAME = 'native';

    /**
     * Starts a cookie session for the browser flow. The caller regenerates the
     * session id afterwards.
     */
    public function startSessionFor(string $accountId): void
    {
        Auth::login($this->userOrFail($accountId), remember: true);
    }

    /**
     * Issues a bearer token for a native client.
     */
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
