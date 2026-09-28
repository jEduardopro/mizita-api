<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\ValueObjects\BookingLinkBlocker;

final readonly class BookingLinkStatus
{
    /**
     * @param  list<BookingLinkBlocker>  $blockers
     */
    public function __construct(
        public ?BookingLinkData $link,
        public array $blockers,
    ) {}
}
