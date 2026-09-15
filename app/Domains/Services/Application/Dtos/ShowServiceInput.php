<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Exceptions\ServiceNotFound;

final readonly class ShowServiceInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $serviceId,
    ) {}

    /**
     * @throws ServiceNotFound
     */
    public function validate(): void
    {
        $this->validateServiceId();
    }

    private function validateServiceId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->serviceId) !== 1) {
            throw ServiceNotFound::withId($this->serviceId);
        }
    }
}
