<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\ValueObjects\RemovalBlocker;

final readonly class TeamMemberRemovalData
{
    public function __construct(
        public ?RemovalBlocker $blocker,
    ) {}

    public function removable(): bool
    {
        return $this->blocker === null;
    }
}
