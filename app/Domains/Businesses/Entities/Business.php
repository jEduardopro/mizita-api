<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Entities;

use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Domains\Businesses\ValueObjects\CurrencyCode;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
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
