<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Exceptions\CustomerNotFound;

final readonly class ShowCustomerInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $customerId,
    ) {}

    /**
     * @throws CustomerNotFound
     */
    public function validate(): void
    {
        $this->validateCustomerId();
    }

    private function validateCustomerId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->customerId) !== 1) {
            throw CustomerNotFound::withId($this->customerId);
        }
    }
}
