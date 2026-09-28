<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\BusinessDirectory;
use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;

final class FakeBusinessDirectory implements BusinessDirectory
{
    /** @var array<string, string> */
    private array $idsBySlug = [];

    /** @var array<string, string> */
    private array $timezonesById = [];

    /** @var list<string> */
    public array $slugLookups = [];

    /** @var list<string> */
    public array $timezoneLookups = [];

    public function with(string $slug, string $businessId, string $timezone): self
    {
        $this->idsBySlug[$slug] = $businessId;
        $this->timezonesById[$businessId] = $timezone;

        return $this;
    }

    public function idForSlug(string $slug): string
    {
        $this->slugLookups[] = $slug;

        return $this->idsBySlug[$slug] ?? throw SubscriptionBusinessNotFound::withSlug($slug);
    }

    public function timezoneOf(string $businessId): string
    {
        $this->timezoneLookups[] = $businessId;

        return $this->timezonesById[$businessId] ?? throw SubscriptionBusinessNotFound::withId($businessId);
    }
}
