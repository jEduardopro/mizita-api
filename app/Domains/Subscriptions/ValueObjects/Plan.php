<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use App\Shared\ValueObjects\CurrencyCode;

enum Plan: string
{
    private const FREE_LIST_PRICE_MINOR_UNITS = 0;

    private const COMPLETE_LIST_PRICE_MINOR_UNITS = 20000;

    case Free = 'free';

    case Complete = 'complete';

    public function entitlements(): PlanEntitlements
    {
        return match ($this) {
            self::Free => PlanEntitlements::free(),
            self::Complete => PlanEntitlements::complete(),
        };
    }

    public function isGrantable(): bool
    {
        return match ($this) {
            self::Free => false,
            self::Complete => true,
        };
    }

    public function listPrice(): SubscriptionPrice
    {
        $amount = match ($this) {
            self::Free => self::FREE_LIST_PRICE_MINOR_UNITS,
            self::Complete => self::COMPLETE_LIST_PRICE_MINOR_UNITS,
        };

        return SubscriptionPrice::of($amount, CurrencyCode::default());
    }
}
