<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use App\Domains\Availability\Exceptions\InvalidBookingBlock;
use App\Domains\Availability\Exceptions\StaffMemberNotBookable;

final readonly class BookableService
{
    private const NO_LEADING_BUFFER = 0;

    /**
     * @param  list<string>  $staffIds
     */
    public function __construct(
        public string $id,
        public int $durationMinutes,
        public int $bufferAfterMinutes,
        public array $staffIds,
    ) {}

    /**
     * @throws StaffMemberNotBookable
     * @throws InvalidBookingBlock
     */
    public function blockFor(string $staffId): BookingBlock
    {
        if (! in_array($staffId, $this->staffIds, true)) {
            throw StaffMemberNotBookable::forService($staffId, $this->id);
        }

        return BookingBlock::lasting(
            durationMinutes: $this->durationMinutes,
            bufferBeforeMinutes: self::NO_LEADING_BUFFER,
            bufferAfterMinutes: $this->bufferAfterMinutes,
        );
    }
}
