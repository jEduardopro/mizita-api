<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

final readonly class UnreadNotificationCountData
{
    public function __construct(
        public int $count,
    ) {}
}
