<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Events\AccountRegistered;
use App\Domains\Accounts\Events\SocialIdentityLinked;

final readonly class AuthenticationOutcome
{
    /**
     * @param  list<AccountRegistered|SocialIdentityLinked>  $events
     * @param  list<string>  $accountsToSignOut
     */
    public function __construct(
        public AuthenticatedAccountData $account,
        public array $events,
        public array $accountsToSignOut = [],
    ) {}
}
