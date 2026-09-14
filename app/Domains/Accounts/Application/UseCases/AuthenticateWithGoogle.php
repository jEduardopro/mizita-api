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

/**
 * No BusinessContext: Accounts is a root domain. An account exists before, and
 * independently of, any business, so binding a tenant here would be wrong.
 */
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

        // Recognising a returning person by the provider's subject, never by
        // their email: a subject is permanent, an address can be reassigned.
        if ($identity !== null) {
            return AuthenticatedAccountData::forExistingAccount(
                $this->accounts->findById($identity->accountId),
            );
        }

        // The account takeover guard. An unverified Google address proves
        // nothing, so nothing is written and nothing is announced.
        if (! $input->emailVerified) {
            throw GoogleEmailNotVerified::forEmail($input->email);
        }

        try {
            $outcome = $this->transactions->run(
                fn (): AuthenticationOutcome => $this->linkOrRegister($input),
            );

            // Announced only now that the work has committed. Dispatching
            // inside the closure would deliver AccountRegistered for a row a
            // later rollback took away, and a listener cannot un-send an email.
            foreach ($outcome->events as $event) {
                $this->events->dispatch($event);
            }

            return $outcome->account;
        } catch (AccountAlreadyRegistered|SocialIdentityAlreadyLinked $conflict) {
            // Two concurrent sign ins for the same new Google user both saw an
            // empty table and both tried to write. The recovery has to happen
            // out here: Postgres aborts a transaction at the first failed
            // statement, so nothing further can run inside the closure above.
            //
            // Both callers wanted the same outcome - this person is signed in -
            // so the loser adopts the winner's rows instead of being told about
            // a conflict it has no way to act on.
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

    /**
     * Exactly one attempt. If the rows still are not there, the unique index
     * fired for some reason other than a race and a retry loop would hide that,
     * so the original failure is rethrown untouched.
     *
     * The verified-email guard is deliberately not repeated: this path is only
     * reachable once that guard has passed, and re-checking would make it look
     * like a second way in.
     */
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

        // Nothing this request created, so isNewAccount stays false either way.
        if ($account !== null) {
            return AuthenticatedAccountData::forExistingAccount($account);
        }

        throw $conflict;
    }

    private function claim(Account $account, string $googleUserId): AuthenticationOutcome
    {
        // Getting this far means the guard above proved Google verified the
        // address, so control of it is proven for this account too.
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
            // Order is the contract: an account is announced before anything
            // that refers to it.
            [$registered, $linked],
        );
    }

    /** Hands the event back rather than dispatching it, for the caller to fire once the work has committed. */
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
