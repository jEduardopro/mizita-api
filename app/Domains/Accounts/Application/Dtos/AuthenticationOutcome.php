<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Events\AccountRegistered;
use App\Domains\Accounts\Events\SocialIdentityLinked;

/**
 * Carrying the events out instead of dispatching them in place is what keeps a
 * rolled back transaction silent: the list is discarded with the rest of the
 * work. It also keeps the use case stateless.
 */
final readonly class AuthenticationOutcome
{
    /**
     * @param  list<AccountRegistered|SocialIdentityLinked>  $events  in the order they must be announced
     */
    public function __construct(
        public AuthenticatedAccountData $account,
        public array $events,
    ) {}
}
