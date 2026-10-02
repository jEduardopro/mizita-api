<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\Dtos;

use App\Domains\Platform\Exceptions\ImpersonatedBusinessNotFound;

final readonly class StartImpersonationInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $adminId,
        public string $businessId,
    ) {}

    /**
     * @throws ImpersonatedBusinessNotFound
     */
    public function validate(): void
    {
        $this->validateBusinessId();
    }

    private function validateBusinessId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->businessId) !== 1) {
            throw ImpersonatedBusinessNotFound::withId($this->businessId);
        }
    }
}
