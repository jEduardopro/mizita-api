<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

final readonly class PlanEntitlements
{
    public const FREE_ACTIVE_SERVICE_LIMIT = 3;

    public function __construct(
        public bool $includesTeam,
        public ?int $maxActiveServices,
        public bool $includesBookingRules,
        public bool $includesCalendarSync,
    ) {}

    public static function free(): self
    {
        return new self(
            includesTeam: false,
            maxActiveServices: self::FREE_ACTIVE_SERVICE_LIMIT,
            includesBookingRules: false,
            includesCalendarSync: false,
        );
    }

    public static function complete(): self
    {
        return new self(
            includesTeam: true,
            maxActiveServices: null,
            includesBookingRules: true,
            includesCalendarSync: true,
        );
    }
}
