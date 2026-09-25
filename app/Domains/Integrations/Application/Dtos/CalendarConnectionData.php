<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use DateTimeImmutable;

final readonly class CalendarConnectionData
{
    public function __construct(
        public string $id,
        public ConnectionStatus $status,
        public string $accountEmail,
        public DateTimeImmutable $connectedAt,
    ) {}

    public static function fromEntity(CalendarConnection $connection): self
    {
        return new self(
            id: $connection->id,
            status: $connection->status(),
            accountEmail: $connection->accountEmail(),
            connectedAt: $connection->connectedAt(),
        );
    }
}
