<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\Events\SubscriptionStarted;

enum SubscriptionTransition: string
{
    case Started = 'started';

    case Ended = 'ended';

    case Unchanged = 'unchanged';

    public static function between(bool $entitledBefore, bool $entitledAfter): self
    {
        if ($entitledBefore === $entitledAfter) {
            return self::Unchanged;
        }

        return $entitledAfter ? self::Started : self::Ended;
    }

    /**
     * @return list<SubscriptionStarted|SubscriptionEnded>
     */
    public function eventsFor(Subscription $subscription): array
    {
        return match ($this) {
            self::Started => [new SubscriptionStarted($subscription->id, $subscription->businessId)],
            self::Ended => [new SubscriptionEnded($subscription->id, $subscription->businessId)],
            self::Unchanged => [],
        };
    }
}
