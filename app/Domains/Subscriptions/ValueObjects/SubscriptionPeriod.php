<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPeriod;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotExtendable;
use DateTimeImmutable;

final readonly class SubscriptionPeriod
{
    private function __construct(
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
    ) {}

    /**
     * @throws InvalidSubscriptionPeriod
     */
    public static function between(DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        if ($endsAt !== null && $endsAt < $startsAt) {
            throw InvalidSubscriptionPeriod::inverted();
        }

        return new self($startsAt, $endsAt);
    }

    public static function restore(DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        return new self($startsAt, $endsAt);
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        if ($instant < $this->startsAt) {
            return false;
        }

        return $this->endsAt === null || $instant < $this->endsAt;
    }

    public function hasEndedBy(DateTimeImmutable $now): bool
    {
        return $this->endsAt !== null && $this->endsAt <= $now;
    }

    /**
     * @throws SubscriptionNotExtendable
     */
    public function extendedUntil(DateTimeImmutable $endsAt): self
    {
        if ($this->endsAt === null) {
            throw SubscriptionNotExtendable::openEnded();
        }

        if ($endsAt <= $this->endsAt) {
            throw SubscriptionNotExtendable::notAnExtension();
        }

        return new self($this->startsAt, $endsAt);
    }

    public function truncatedAt(DateTimeImmutable $now): self
    {
        $cut = max($this->startsAt, $now);

        if ($this->endsAt !== null && $this->endsAt < $cut) {
            return $this;
        }

        return new self($this->startsAt, $cut);
    }
}
