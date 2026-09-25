<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Entities;

use App\Domains\Businesses\Exceptions\BusinessAlreadyClosed;
use App\Domains\Businesses\Exceptions\BusinessNotClosed;
use App\Domains\Businesses\Exceptions\BusinessNotDueForPurge;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ClosureRetention;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\Reopening;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;

final class Business
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private Slug $slug,
        private string $industryId,
        private Timezone $timezone,
        private ?ContactEmail $contactEmail,
        private ?About $about,
        private CurrencyCode $currency,
        public readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $closedAt,
        private ?string $closedByAccountId,
        private ?DateTimeImmutable $purgedAt,
    ) {}

    /**
     * @throws InvalidBusinessName
     */
    public static function create(
        string $id,
        string $name,
        Slug $slug,
        string $industryId,
        Timezone $timezone,
        DateTimeImmutable $now,
        ?ContactEmail $contactEmail = null,
        ?About $about = null,
        ?CurrencyCode $currency = null,
    ): self {
        return new self(
            id: $id,
            name: self::acceptableName($name),
            slug: $slug,
            industryId: $industryId,
            timezone: $timezone,
            contactEmail: $contactEmail,
            about: $about,
            currency: $currency ?? CurrencyCode::default(),
            createdAt: $now,
            closedAt: null,
            closedByAccountId: null,
            purgedAt: null,
        );
    }

    public static function restore(
        string $id,
        string $name,
        Slug $slug,
        string $industryId,
        Timezone $timezone,
        DateTimeImmutable $createdAt,
        ?ContactEmail $contactEmail = null,
        ?About $about = null,
        ?CurrencyCode $currency = null,
        ?DateTimeImmutable $closedAt = null,
        ?string $closedByAccountId = null,
        ?DateTimeImmutable $purgedAt = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            industryId: $industryId,
            timezone: $timezone,
            contactEmail: $contactEmail,
            about: $about,
            currency: $currency ?? CurrencyCode::default(),
            createdAt: $createdAt,
            closedAt: $closedAt,
            closedByAccountId: $closedByAccountId,
            purgedAt: $purgedAt,
        );
    }

    /**
     * @throws InvalidBusinessName
     */
    public function rename(string $name): void
    {
        $this->name = self::acceptableName($name);
    }

    public function changeSlug(Slug $slug): void
    {
        $this->slug = $slug;
    }

    public function reclassify(string $industryId): void
    {
        $this->industryId = $industryId;
    }

    public function changeTimezone(Timezone $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function changeContactEmail(?ContactEmail $email): void
    {
        $this->contactEmail = $email;
    }

    public function describeAs(?About $about): void
    {
        $this->about = $about;
    }

    public function changeCurrency(CurrencyCode $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @throws BusinessAlreadyClosed
     */
    public function close(string $ownerAccountId, DateTimeImmutable $now): void
    {
        if ($this->isClosed()) {
            throw BusinessAlreadyClosed::withId($this->id);
        }

        $this->closedAt = $now;
        $this->closedByAccountId = $ownerAccountId;
    }

    /**
     * @throws BusinessNotClosed
     */
    public function reopen(string $ownerAccountId): Reopening
    {
        if (! $this->isClosed()) {
            throw BusinessNotClosed::withId($this->id);
        }

        if ($this->closedByAccountId !== $ownerAccountId) {
            throw BusinessNotClosed::byAccount($this->id, $ownerAccountId);
        }

        $reopening = $this->isPurged() ? Reopening::afterPurge() : Reopening::withDataIntact();

        $this->closedAt = null;
        $this->closedByAccountId = null;
        $this->purgedAt = null;

        return $reopening;
    }

    /**
     * @throws BusinessNotClosed
     * @throws BusinessNotDueForPurge
     */
    public function markPurged(DateTimeImmutable $now): void
    {
        $purgeDueAt = $this->purgeScheduledAt();

        if ($purgeDueAt === null) {
            throw BusinessNotClosed::withId($this->id);
        }

        if ($now < $purgeDueAt) {
            throw BusinessNotDueForPurge::until($this->id, $purgeDueAt);
        }

        if ($this->isPurged()) {
            return;
        }

        $this->purgedAt = $now;
    }

    public function isClosed(): bool
    {
        return $this->closedAt !== null;
    }

    public function isPurged(): bool
    {
        return $this->purgedAt !== null;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedByAccountId(): ?string
    {
        return $this->closedByAccountId;
    }

    public function purgedAt(): ?DateTimeImmutable
    {
        return $this->purgedAt;
    }

    public function purgeScheduledAt(): ?DateTimeImmutable
    {
        if ($this->closedAt === null) {
            return null;
        }

        return ClosureRetention::purgeDueAt($this->closedAt);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug->value;
    }

    public function industryId(): string
    {
        return $this->industryId;
    }

    public function timezone(): string
    {
        return $this->timezone->value;
    }

    public function contactEmail(): ?string
    {
        return $this->contactEmail?->value;
    }

    public function about(): ?string
    {
        return $this->about?->value;
    }

    public function currency(): string
    {
        return $this->currency->value;
    }

    /**
     * @throws InvalidBusinessName
     */
    private static function acceptableName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidBusinessName::empty();
        }

        return $name;
    }
}
