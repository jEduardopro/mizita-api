<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;
use App\Domains\Integrations\Exceptions\InvalidBusyWindow;
use DateTimeImmutable;

final readonly class ListCalendarBusyIntervalsInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $businessId,
        public string $staffMemberId,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {}

    /**
     * @throws CalendarBusinessNotFound
     * @throws CalendarOwnerNotFound
     * @throws InvalidBusyWindow
     */
    public function validate(): void
    {
        $this->validateBusinessId();
        $this->validateStaffMemberId();
        $this->validateWindow();
    }

    private function validateBusinessId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->businessId) !== 1) {
            throw CalendarBusinessNotFound::withId($this->businessId);
        }
    }

    private function validateStaffMemberId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->staffMemberId) !== 1) {
            throw CalendarOwnerNotFound::withId($this->staffMemberId);
        }
    }

    private function validateWindow(): void
    {
        if ($this->to <= $this->from) {
            throw InvalidBusyWindow::endsBeforeItStarts();
        }
    }
}
