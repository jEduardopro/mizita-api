<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class OwnerRegistration
{
    /**
     * @param  list<object>  $events
     */
    public function __construct(
        public string $staffMemberId,
        public array $events,
    ) {}
}
