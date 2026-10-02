<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\ValueObjects\Impersonation;
use DateTimeImmutable;

interface ImpersonationAuditTrail
{
    public function recordStarted(Impersonation $impersonation, ?string $ipAddress): void;

    public function recordEnded(string $impersonationId, DateTimeImmutable $endedAt): void;
}
