<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

final readonly class NotificationSubject
{
    public function __construct(
        public NotificationSubjectType $type,
        public string $id,
    ) {}
}
