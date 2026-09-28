<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\CalendarSyncAllowance;

final class FakeCalendarSyncAllowance implements CalendarSyncAllowance
{
    /**
     * @var list<string>
     */
    public array $lookups = [];

    private function __construct(
        private readonly bool $includesCalendarSync,
    ) {}

    public static function completePlan(): self
    {
        return new self(true);
    }

    public static function freePlan(): self
    {
        return new self(false);
    }

    public function includesCalendarSync(string $businessId): bool
    {
        $this->lookups[] = $businessId;

        return $this->includesCalendarSync;
    }
}
