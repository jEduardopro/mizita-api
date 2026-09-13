<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

/**
 * What registering a membership produced: the row to answer with, and the
 * events it earned the right to announce.
 *
 * The events are carried out instead of dispatched in place because this use
 * case runs inside somebody else's transaction. Announcing from in there would
 * deliver an event for a row a later rollback takes away, and a listener cannot
 * un-send an email. Returning them also keeps the use case stateless.
 *
 * Internal to the application layer: it never crosses the HTTP boundary.
 */
final readonly class StaffMemberRegistration
{
    /**
     * @param  list<object>  $events  in the order they must be announced
     */
    public function __construct(
        public StaffMemberData $member,
        public array $events,
    ) {}
}
