<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Auth;

use App\Domains\Accounts\Exceptions\AccountPendingReactivation;
use App\Domains\Accounts\Infrastructure\Http\Controllers\AccountReactivationController;
use App\Http\Responses\JsonFailureRendering;
use App\Models\User;
use App\Shared\Contracts\Clock;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

final class PasswordCredentialsAuthenticator
{
    private const PASSWORD_FIELD = 'password';

    public function __construct(
        private readonly Hasher $hasher,
        private readonly PendingReactivation $pendingReactivation,
        private readonly Clock $clock,
        private readonly Repository $config,
    ) {}

    /**
     * @throws AccountPendingReactivation
     * @throws HttpResponseException
     */
    public function authenticate(Request $request): ?User
    {
        $email = mb_strtolower(trim((string) $request->input(Fortify::username(), '')));
        $password = $request->input(self::PASSWORD_FIELD);

        if ($email === '' || ! is_string($password) || $password === '') {
            return null;
        }

        $user = User::withTrashed()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user instanceof User || ! $this->passwordMatches($user, $password)) {
            return null;
        }

        $this->rehashPasswordIfRequired($user, $password);

        if ($user->trashed()) {
            $this->holdForReactivation($request, $user);
        }

        return $user;
    }

    private function passwordMatches(User $user, string $password): bool
    {
        $hashedPassword = $user->getAuthPassword();

        return is_string($hashedPassword) && $this->hasher->check($password, $hashedPassword);
    }

    private function rehashPasswordIfRequired(User $user, string $password): void
    {
        if (! $this->config->get('hashing.rehash_on_login', true)) {
            return;
        }

        if (! $this->hasher->needsRehash($user->getAuthPassword())) {
            return;
        }

        $user->forceFill([self::PASSWORD_FIELD => $this->hasher->make($password)])->save();
    }

    /**
     * @throws AccountPendingReactivation
     * @throws HttpResponseException
     */
    private function holdForReactivation(Request $request, User $user): never
    {
        $accountId = (string) $user->uuid;

        $this->pendingReactivation->remember($accountId, $this->clock->now());

        if (JsonFailureRendering::appliesTo($request)) {
            throw AccountPendingReactivation::forAccount($accountId);
        }

        throw new HttpResponseException(redirect()->route(AccountReactivationController::REACTIVATE_ROUTE));
    }
}
