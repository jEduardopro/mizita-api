<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Events\AccountRegistered;
use App\Domains\Accounts\Events\SocialIdentityLinked;

/**
 * What a unit of work produced: the account to answer with, and the events it
 * earned the right to announce.
 *
 * Carrying the events out instead of dispatching them in place is what keeps a
 * rolled back transaction silent - the list is simply discarded with the rest
 * of the work. Returning them also keeps the use case stateless, where
 * accumulating into a property would leak between calls to handle().
 *
 * Internal to the application layer: it never crosses the HTTP boundary.
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
