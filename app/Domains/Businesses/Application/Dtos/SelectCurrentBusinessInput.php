<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\InvalidBusinessSelection;

final readonly class SelectCurrentBusinessInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $accountId,
        public string $businessId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        $businessId = $payload['business_id'] ?? '';

        return new self(
            accountId: $accountId,
            businessId: is_string($businessId) ? strtolower(trim($businessId)) : '',
        );
    }

    /**
     * @throws InvalidBusinessSelection
     */
    public function validate(): void
    {
        $this->validateBusinessId();
    }

    private function validateBusinessId(): void
    {
        if ($this->businessId === '') {
            throw InvalidBusinessSelection::missing();
        }

        if (preg_match(self::UUID_PATTERN, $this->businessId) !== 1) {
            throw InvalidBusinessSelection::malformed($this->businessId);
        }
    }
}
