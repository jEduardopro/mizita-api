<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class Reopening
{
    private function __construct(
        private bool $reprovisioningRequired,
    ) {}

    public static function withDataIntact(): self
    {
        return new self(false);
    }

    public static function afterPurge(): self
    {
        return new self(true);
    }

    public function requiresReprovisioning(): bool
    {
        return $this->reprovisioningRequired;
    }
}
