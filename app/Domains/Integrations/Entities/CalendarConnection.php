<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Entities;

use App\Domains\Integrations\Exceptions\CalendarAlreadyConnected;
use App\Domains\Integrations\Exceptions\InvalidCalendarConnection;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use DateTimeImmutable;

final class CalendarConnection
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $staffMemberId,
        public readonly CalendarProvider $provider,
        private string $accountEmail,
        private string $externalCalendarId,
        private ConnectionStatus $status,
        private DateTimeImmutable $connectedAt,
    ) {}

    /**
     * @throws InvalidCalendarConnection
     */
    public static function connect(
        string $id,
        string $businessId,
        string $staffMemberId,
        CalendarProvider $provider,
        string $accountEmail,
        string $externalCalendarId,
        DateTimeImmutable $now,
    ): self {
        self::assertComplete($accountEmail, $externalCalendarId);

        return new self(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            provider: $provider,
            accountEmail: trim($accountEmail),
            externalCalendarId: $externalCalendarId,
            status: ConnectionStatus::Connected,
            connectedAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $staffMemberId,
        CalendarProvider $provider,
        string $accountEmail,
        string $externalCalendarId,
        ConnectionStatus $status,
        DateTimeImmutable $connectedAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            provider: $provider,
            accountEmail: $accountEmail,
            externalCalendarId: $externalCalendarId,
            status: $status,
            connectedAt: $connectedAt,
        );
    }

    /**
     * @throws CalendarAlreadyConnected
     */
    public function assertAwaitingReconnect(): void
    {
        if ($this->status === ConnectionStatus::Connected) {
            throw CalendarAlreadyConnected::forStaffMember($this->staffMemberId);
        }
    }

    /**
     * @throws CalendarAlreadyConnected
     * @throws InvalidCalendarConnection
     */
    public function reconnect(string $accountEmail, string $externalCalendarId, DateTimeImmutable $now): void
    {
        $this->assertAwaitingReconnect();
        self::assertComplete($accountEmail, $externalCalendarId);

        $this->accountEmail = trim($accountEmail);
        $this->externalCalendarId = $externalCalendarId;
        $this->status = ConnectionStatus::Connected;
        $this->connectedAt = $now;
    }

    public function requireReconnect(): void
    {
        $this->status = ConnectionStatus::NeedsReconnect;
    }

    public function acceptsSync(): bool
    {
        return $this->status === ConnectionStatus::Connected;
    }

    public function accountEmail(): string
    {
        return $this->accountEmail;
    }

    public function externalCalendarId(): string
    {
        return $this->externalCalendarId;
    }

    public function status(): ConnectionStatus
    {
        return $this->status;
    }

    public function connectedAt(): DateTimeImmutable
    {
        return $this->connectedAt;
    }

    /**
     * @throws InvalidCalendarConnection
     */
    private static function assertComplete(string $accountEmail, string $externalCalendarId): void
    {
        if (trim($accountEmail) === '') {
            throw InvalidCalendarConnection::missingAccountEmail();
        }

        if (trim($externalCalendarId) === '') {
            throw InvalidCalendarConnection::missingCalendar();
        }
    }
}
