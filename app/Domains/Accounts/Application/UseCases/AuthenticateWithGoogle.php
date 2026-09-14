<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\Dtos\AuthenticationOutcome;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\SocialIdentityRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Events\AccountRegistered;
use App\Domains\Accounts\Events\SocialIdentityLinked;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\Exceptions\GoogleEmailNotVerified;
use App\Domains\Accounts\Exceptions\SocialIdentityAlreadyLinked;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use Illuminate\Contracts\Events\Dispatcher;

final class AuthenticateWithGoogle
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly SocialIdentityRepository $socialIdentities,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
        private readonly Dispatcher $events,
    ) {}

    public function handle(AuthenticateWithGoogleInput $input): AuthenticatedAccountData
    {
        $identity = $this->socialIdentities->findByProviderUserId(
            SocialProvider::Google,
            $input->googleUserId,
        );

        if ($identity !== null) {
            return AuthenticatedAccountData::forExistingAccount(
                $this->accounts->findById($identity->accountId),
            );
        }

        if (! $input->emailVerified) {
            throw GoogleEmailNotVerified::forEmail($input->email);
        }

        try {
            $outcome = $this->transactions->run(
                fn (): AuthenticationOutcome => $this->linkOrRegister($input),
            );

            foreach ($outcome->events as $event) {
                $this->events->dispatch($event);
            }

            return $outcome->account;
        } catch (AccountAlreadyRegistered|SocialIdentityAlreadyLinked $conflict) {
            return $this->adoptConcurrentRegistration($input, $conflict);
        }
    }

    private function linkOrRegister(AuthenticateWithGoogleInput $input): AuthenticationOutcome
    {
        $email = $this->normalizedEmail($input->email);
        $account = $this->accounts->findByEmail($email);

        if ($account !== null) {
            return $this->claim($account, $input->googleUserId);
        }

        return $this->register($input, $email);
    }

    private function adoptConcurrentRegistration(
        AuthenticateWithGoogleInput $input,
        AccountAlreadyRegistered|SocialIdentityAlreadyLinked $conflict,
    ): AuthenticatedAccountData {
        $identity = $this->socialIdentities->findByProviderUserId(
            SocialProvider::Google,
            $input->googleUserId,
        );

        if ($identity !== null) {
            return AuthenticatedAccountData::forExistingAccount(
                $this->accounts->findById($identity->accountId),
            );
        }

        $account = $this->accounts->findByEmail($this->normalizedEmail($input->email));

        if ($account !== null) {
            return AuthenticatedAccountData::forExistingAccount($account);
        }

        throw $conflict;
    }

    private function claim(Account $account, string $googleUserId): AuthenticationOutcome
    {
        $account->verifyEmail($this->clock->now());
        $this->accounts->save($account);

        return new AuthenticationOutcome(
            AuthenticatedAccountData::forExistingAccount($account),
            [$this->link($account, $googleUserId)],
        );
    }

    private function register(AuthenticateWithGoogleInput $input, string $email): AuthenticationOutcome
    {
        $account = Account::registerWithVerifiedEmail(
            id: $this->ids->next(),
            name: $input->name,
            email: $email,
            now: $this->clock->now(),
        );

        $this->accounts->save($account);

        $registered = new AccountRegistered($account->id);
        $linked = $this->link($account, $input->googleUserId);

        return new AuthenticationOutcome(
            AuthenticatedAccountData::forNewAccount($account),
            [$registered, $linked],
        );
    }

    private function link(Account $account, string $googleUserId): SocialIdentityLinked
    {
        $identity = SocialIdentity::link(
            id: $this->ids->next(),
            accountId: $account->id,
            provider: SocialProvider::Google,
            providerUserId: $googleUserId,
            now: $this->clock->now(),
        );

        $this->socialIdentities->save($identity);

        return new SocialIdentityLinked(
            socialIdentityId: $identity->id,
            accountId: $account->id,
            provider: SocialProvider::Google,
        );
    }

    private function normalizedEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
