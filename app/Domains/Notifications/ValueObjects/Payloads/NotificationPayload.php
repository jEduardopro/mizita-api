<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects\Payloads;

use App\Domains\Notifications\ValueObjects\NotificationSubject;
use App\Domains\Notifications\ValueObjects\NotificationType;

interface NotificationPayload
{
    /**
     * @param  array<array-key, mixed>  $snapshot
     */
    public static function fromArray(array $snapshot): self;

    public function type(): NotificationType;

    public function subject(): NotificationSubject;

    public function idempotencyKey(string $eventId): string;

    public function collapseKey(string $eventId): string;

    /**
     * @return array<string, array<string, string>>
     */
    public function toArray(): array;
}
