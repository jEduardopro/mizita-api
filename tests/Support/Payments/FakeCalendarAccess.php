<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\CalendarAccess;
use App\Domains\Payments\Exceptions\PaymentAccountNotFound;
use App\Domains\Payments\ValueObjects\CalendarScope;

final class FakeCalendarAccess implements CalendarAccess
{
    /**
     * @var list<array{businessId: string, accountId: string}>
     */
    public array $lookups = [];

    private function __construct(
        private readonly ?CalendarScope $scope,
    ) {}

    public static function everyone(): self
    {
        return new self(CalendarScope::everyone());
    }

    public static function ownedBy(string $staffMemberId): self
    {
        return new self(CalendarScope::ownedBy($staffMemberId));
    }

    public static function refusing(): self
    {
        return new self(null);
    }

    public function scopeFor(string $businessId, string $accountId): CalendarScope
    {
        $this->lookups[] = ['businessId' => $businessId, 'accountId' => $accountId];

        return $this->scope ?? throw PaymentAccountNotFound::withId($accountId);
    }
}
