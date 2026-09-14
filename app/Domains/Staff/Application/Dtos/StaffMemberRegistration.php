<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

/**
 * The events are carried out instead of dispatched in place because this use
 * case runs inside somebody else's transaction: announcing from in there would
 * deliver an event for a row a later rollback takes away.
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
