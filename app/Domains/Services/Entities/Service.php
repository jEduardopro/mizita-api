<?php

declare(strict_types=1);

namespace App\Domains\Services\Entities;

use App\Domains\Services\Exceptions\InvalidServiceDescription;
use App\Domains\Services\Exceptions\InvalidServiceName;
use App\Domains\Services\Exceptions\ServiceAlreadyActive;
use App\Domains\Services\Exceptions\ServiceAlreadyInactive;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\Price;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\Slug;
use DateTimeImmutable;

final class Service
{
    public const MINIMUM_NAME_LENGTH = 2;

    public const MAXIMUM_NAME_LENGTH = 120;

    public const MAXIMUM_DESCRIPTION_LENGTH = 2000;

    public const MAXIMUM_STAFF_MEMBERS = 50;

    /**
     * @param  list<string>  $staffIds
     */
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        private string $name,
        private Slug $slug,
        private ?string $description,
        private Duration $duration,
        private Buffer $buffer,
        private Price $price,
        private ServiceColor $color,
        private bool $active,
        private array $staffIds,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param  list<string>  $staffIds
     *
     * @throws InvalidServiceName
     * @throws InvalidServiceDescription
     * @throws UnknownStaffMember
     */
    public static function create(
        string $id,
        string $businessId,
        string $name,
        Slug $slug,
        ?string $description,
        Duration $duration,
        Buffer $buffer,
        Price $price,
        ServiceColor $color,
        bool $active,
        array $staffIds,
        DateTimeImmutable $now,
    ): self {
        $service = new self(
            id: $id,
            businessId: $businessId,
            name: self::acceptableName($name),
            slug: $slug,
            description: self::acceptableDescription($description),
            duration: $duration,
            buffer: $buffer,
            price: $price,
            color: $color,
            active: $active,
            staffIds: [],
            createdAt: $now,
        );

        $service->assignStaff($staffIds);

        return $service;
    }

    /**
     * @param  list<string>  $staffIds
     */
    public static function restore(
        string $id,
        string $businessId,
        string $name,
        Slug $slug,
        ?string $description,
        Duration $duration,
        Buffer $buffer,
        Price $price,
        ServiceColor $color,
        bool $active,
        array $staffIds,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            name: $name,
            slug: $slug,
            description: $description,
            duration: $duration,
            buffer: $buffer,
            price: $price,
            color: $color,
            active: $active,
            staffIds: $staffIds,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws InvalidServiceName
     */
    public function rename(string $name, Slug $slug): void
    {
        $this->name = self::acceptableName($name);
        $this->slug = $slug;
    }

    /**
     * @throws InvalidServiceDescription
     */
    public function redescribe(?string $description): void
    {
        $this->description = self::acceptableDescription($description);
    }

    public function reschedule(Duration $duration, Buffer $buffer): void
    {
        $this->duration = $duration;
        $this->buffer = $buffer;
    }

    public function reprice(Price $price): void
    {
        $this->price = $price;
    }

    public function recolor(ServiceColor $color): void
    {
        $this->color = $color;
    }

    /**
     * @param  list<string>  $staffIds
     *
     * @throws UnknownStaffMember
     */
    public function assignStaff(array $staffIds): void
    {
        $assigned = array_values(array_unique($staffIds));

        if (count($assigned) > self::MAXIMUM_STAFF_MEMBERS) {
            throw UnknownStaffMember::amongSelected();
        }

        $this->staffIds = $assigned;
    }

    /**
     * @throws ServiceAlreadyActive
     */
    public function activate(): void
    {
        if ($this->active) {
            throw ServiceAlreadyActive::for($this->id);
        }

        $this->active = true;
    }

    /**
     * @throws ServiceAlreadyInactive
     */
    public function deactivate(): void
    {
        if (! $this->active) {
            throw ServiceAlreadyInactive::for($this->id);
        }

        $this->active = false;
    }

    /**
     * @throws InvalidServiceName
     */
    public function duplicateAs(string $id, string $name, Slug $slug, DateTimeImmutable $now): self
    {
        return new self(
            id: $id,
            businessId: $this->businessId,
            name: self::acceptableName($name),
            slug: $slug,
            description: $this->description,
            duration: $this->duration,
            buffer: $this->buffer,
            price: $this->price,
            color: $this->color,
            active: false,
            staffIds: $this->staffIds,
            createdAt: $now,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug->value;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function durationMinutes(): int
    {
        return $this->duration->minutes;
    }

    public function bufferMinutes(): int
    {
        return $this->buffer->minutes;
    }

    public function price(): string
    {
        return $this->price->amount;
    }

    public function color(): ServiceColor
    {
        return $this->color;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @return list<string>
     */
    public function staffIds(): array
    {
        return $this->staffIds;
    }

    /**
     * @throws InvalidServiceName
     */
    private static function acceptableName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidServiceName::empty();
        }

        if (mb_strlen($name) < self::MINIMUM_NAME_LENGTH) {
            throw InvalidServiceName::tooShort();
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidServiceName::tooLong();
        }

        return $name;
    }

    /**
     * @throws InvalidServiceDescription
     */
    private static function acceptableDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $description = trim($description);

        if ($description === '') {
            return null;
        }

        if (mb_strlen($description) > self::MAXIMUM_DESCRIPTION_LENGTH) {
            throw InvalidServiceDescription::tooLong(self::MAXIMUM_DESCRIPTION_LENGTH);
        }

        return $description;
    }
}
