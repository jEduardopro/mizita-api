<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\InvalidServiceColor;
use App\Domains\Services\Exceptions\InvalidServiceDescription;
use App\Domains\Services\Exceptions\InvalidServiceName;
use App\Domains\Services\Exceptions\UnknownStaffMember;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\Price;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Services\ValueObjects\Slug;

final readonly class CreateServiceInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    /**
     * @param  list<string>  $staffIds
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public int $durationMinutes,
        public int $bufferMinutes,
        public string $price,
        public string $color,
        public bool $active,
        public array $staffIds,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            name: self::textOrEmpty($payload['name'] ?? null),
            description: self::textOrNull($payload['description'] ?? null),
            durationMinutes: self::minutesOrZero($payload['duration_minutes'] ?? null),
            bufferMinutes: self::minutesOrZero($payload['buffer_minutes'] ?? null),
            price: self::textOrEmpty($payload['price'] ?? null),
            color: self::textOrEmpty($payload['color'] ?? null),
            active: (bool) ($payload['active'] ?? true),
            staffIds: self::identifiers($payload['staff_ids'] ?? null),
        );
    }

    /**
     * @throws InvalidServiceName
     * @throws InvalidServiceDescription
     * @throws InvalidServiceColor
     * @throws UnknownStaffMember
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateDescription();
        $this->validateDuration();
        $this->validateBuffer();
        $this->validatePrice();
        $this->validateColor();
        $this->validateStaffIds();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function minutesOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return list<string>
     */
    private static function identifiers(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $identifier): string => is_string($identifier) ? $identifier : '',
            $value,
        ));
    }

    private function validateName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            throw InvalidServiceName::empty();
        }

        if (mb_strlen($name) < Service::MINIMUM_NAME_LENGTH) {
            throw InvalidServiceName::tooShort();
        }

        if (mb_strlen($name) > Service::MAXIMUM_NAME_LENGTH) {
            throw InvalidServiceName::tooLong();
        }

        Slug::fromName($name);
    }

    private function validateDescription(): void
    {
        if ($this->description !== null && mb_strlen(trim($this->description)) > Service::MAXIMUM_DESCRIPTION_LENGTH) {
            throw InvalidServiceDescription::tooLong(Service::MAXIMUM_DESCRIPTION_LENGTH);
        }
    }

    private function validateDuration(): void
    {
        Duration::ofMinutes($this->durationMinutes);
    }

    private function validateBuffer(): void
    {
        Buffer::ofMinutes($this->bufferMinutes);
    }

    private function validatePrice(): void
    {
        Price::fromString($this->price);
    }

    private function validateColor(): void
    {
        if (ServiceColor::tryFrom($this->color) === null) {
            throw InvalidServiceColor::forValue($this->color);
        }
    }

    private function validateStaffIds(): void
    {
        if (count($this->staffIds) > Service::MAXIMUM_STAFF_MEMBERS) {
            throw UnknownStaffMember::amongSelected();
        }

        foreach ($this->staffIds as $staffId) {
            if (preg_match(self::UUID_PATTERN, $staffId) !== 1) {
                throw UnknownStaffMember::amongSelected();
            }
        }
    }
}
