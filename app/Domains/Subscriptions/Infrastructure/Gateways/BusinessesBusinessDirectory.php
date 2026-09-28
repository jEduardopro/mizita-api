<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Subscriptions\Contracts\BusinessDirectory;
use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;

final class BusinessesBusinessDirectory implements BusinessDirectory
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function idForSlug(string $slug): string
    {
        try {
            return $this->businesses->findBySlug($slug)->id;
        } catch (BusinessNotFound) {
            throw SubscriptionBusinessNotFound::withSlug($slug);
        }
    }

    public function timezoneOf(string $businessId): string
    {
        try {
            return $this->businesses->findById($businessId)->timezone();
        } catch (BusinessNotFound) {
            throw SubscriptionBusinessNotFound::withId($businessId);
        }
    }
}
