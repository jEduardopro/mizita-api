<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\ImpersonationStatus;

final class FakeImpersonationStatus implements ImpersonationStatus
{
    public int $descriptions = 0;

    /**
     * @param  array{business_name: string, owner_name: string, expires_at: string}|null  $impersonation
     */
    public function __construct(
        private readonly ?array $impersonation = null,
    ) {}

    public function isActive(): bool
    {
        return $this->impersonation !== null;
    }

    public function describe(): ?array
    {
        $this->descriptions++;

        return $this->impersonation;
    }
}
