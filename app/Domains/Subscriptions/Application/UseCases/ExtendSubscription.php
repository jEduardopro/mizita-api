<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\ExtendSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Contracts\BusinessDirectory;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotFound;
use App\Domains\Subscriptions\ValueObjects\LastIncludedDay;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use DateTimeImmutable;
use DateTimeZone;

final class ExtendSubscription
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BusinessDirectory $businesses,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<SubscriptionData>
     */
    public function handle(ExtendSubscriptionInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $subscription = $this->extend($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(SubscriptionData::fromEntity($subscription));
    }

    private function extend(ExtendSubscriptionInput $input): Subscription
    {
        $businessId = $this->businesses->idForSlug($input->businessSlug()->value);
        $now = $this->clock->now();

        $subscription = $this->inEffectFor($businessId, $now);
        $subscription->extendUntil($this->exclusiveEndOf($input->lastIncludedDay(), $businessId), $now);

        $this->subscriptions->save($subscription);

        return $subscription;
    }

    /**
     * @throws SubscriptionNotFound
     */
    private function inEffectFor(string $businessId, DateTimeImmutable $now): Subscription
    {
        return $this->subscriptions->inEffectFor($businessId, $now)
            ?? throw SubscriptionNotFound::inEffectFor($businessId);
    }

    private function exclusiveEndOf(LastIncludedDay $lastIncludedDay, string $businessId): DateTimeImmutable
    {
        return $lastIncludedDay->exclusiveEndIn(new DateTimeZone($this->businesses->timezoneOf($businessId)));
    }
}
