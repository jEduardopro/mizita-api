<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

final readonly class StaffMemberRegistration
{
    /**
     * @param  list<object>  $events
     */
    public function __construct(
        public StaffMemberData $member,
        public array $events,
    ) {}
}
