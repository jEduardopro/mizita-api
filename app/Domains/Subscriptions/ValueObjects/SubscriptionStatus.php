<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

enum SubscriptionStatus: string
{
    case Incomplete = 'incomplete';

    case Trialing = 'trialing';

    case Active = 'active';

    case PastDue = 'past_due';

    case Canceled = 'canceled';

    case Unpaid = 'unpaid';

    case IncompleteExpired = 'incomplete_expired';

    case Paused = 'paused';

    public function isPaidUp(): bool
    {
        return match ($this) {
            self::Active, self::Trialing => true,
            default => false,
        };
    }

    public function entitles(): bool
    {
        return match ($this) {
            self::Active, self::Trialing, self::PastDue => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Canceled, self::IncompleteExpired => true,
            default => false,
        };
    }
}
