<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Eloquent;

use App\Domains\Platform\Contracts\ImpersonationAuditTrail;
use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformAdminModel;
use App\Domains\Platform\Infrastructure\Eloquent\Models\PlatformImpersonationModel;
use App\Domains\Platform\ValueObjects\Impersonation;
use App\Models\User;
use App\Shared\Contracts\BusinessTeamKey;
use DateTimeImmutable;

final class EloquentImpersonationAuditTrail implements ImpersonationAuditTrail
{
    private const PRIMARY_KEY = 'id';

    public function __construct(
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function recordStarted(Impersonation $impersonation, ?string $ipAddress): void
    {
        PlatformImpersonationModel::query()->create([
            'uuid' => $impersonation->id,
            'platform_admin_id' => $this->adminKeyFor($impersonation->adminId),
            'account_id' => $this->accountKeyFor($impersonation->accountId),
            'business_id' => $this->businessKeys->teamKeyFor($impersonation->businessId),
            'started_at' => $impersonation->startedAt,
            'ip_address' => $ipAddress,
        ]);
    }

    public function recordEnded(string $impersonationId, DateTimeImmutable $endedAt): void
    {
        PlatformImpersonationModel::query()
            ->where('uuid', $impersonationId)
            ->whereNull('ended_at')
            ->update(['ended_at' => $endedAt]);
    }

    private function adminKeyFor(string $adminId): int
    {
        return (int) PlatformAdminModel::query()->where('uuid', $adminId)->valueOrFail(self::PRIMARY_KEY);
    }

    private function accountKeyFor(string $accountId): int
    {
        return (int) User::query()->where('uuid', $accountId)->valueOrFail(self::PRIMARY_KEY);
    }
}
