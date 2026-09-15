<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\InvalidServiceName;
use App\Domains\Services\Exceptions\ServiceNotFound;

final readonly class DuplicateServiceInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $serviceId,
        public ?string $name = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $serviceId): self
    {
        $name = $payload['name'] ?? null;

        return new self(
            serviceId: $serviceId,
            name: is_string($name) && trim($name) !== '' ? $name : null,
        );
    }

    /**
     * @throws ServiceNotFound
     * @throws InvalidServiceName
     */
    public function validate(): void
    {
        $this->validateServiceId();
        $this->validateName();
    }

    private function validateServiceId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->serviceId) !== 1) {
            throw ServiceNotFound::withId($this->serviceId);
        }
    }

    private function validateName(): void
    {
        if ($this->name === null) {
            return;
        }

        $name = trim($this->name);

        if (mb_strlen($name) < Service::MINIMUM_NAME_LENGTH) {
            throw InvalidServiceName::tooShort();
        }

        if (mb_strlen($name) > Service::MAXIMUM_NAME_LENGTH) {
            throw InvalidServiceName::tooLong();
        }
    }
}
