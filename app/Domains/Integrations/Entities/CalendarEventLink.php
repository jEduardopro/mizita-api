<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Entities;

use App\Domains\Integrations\Exceptions\InvalidCalendarEventLink;

final class CalendarEventLink
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $connectionId,
        public readonly string $appointmentId,
        private string $externalEventId,
    ) {}

    /**
     * @throws InvalidCalendarEventLink
     */
    public static function create(
        string $id,
        string $businessId,
        string $connectionId,
        string $appointmentId,
        string $externalEventId,
    ): self {
        self::assertPointsToAnEvent($externalEventId);

        return new self($id, $businessId, $connectionId, $appointmentId, $externalEventId);
    }

    public static function restore(
        string $id,
        string $businessId,
        string $connectionId,
        string $appointmentId,
        string $externalEventId,
    ): self {
        return new self($id, $businessId, $connectionId, $appointmentId, $externalEventId);
    }

    public function belongsTo(string $connectionId): bool
    {
        return $this->connectionId === $connectionId;
    }

    /**
     * @throws InvalidCalendarEventLink
     */
    public function repointTo(string $externalEventId): void
    {
        self::assertPointsToAnEvent($externalEventId);

        $this->externalEventId = $externalEventId;
    }

    public function externalEventId(): string
    {
        return $this->externalEventId;
    }

    /**
     * @throws InvalidCalendarEventLink
     */
    private static function assertPointsToAnEvent(string $externalEventId): void
    {
        if (trim($externalEventId) === '') {
            throw InvalidCalendarEventLink::missingEvent();
        }
    }
}
