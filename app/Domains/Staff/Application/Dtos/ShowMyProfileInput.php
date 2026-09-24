<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\StaffMemberNotFound;

final readonly class ShowMyProfileInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $accountId,
    ) {}

    /**
     * @throws StaffMemberNotFound
     */
    public function validate(): void
    {
        $this->validateAccountId();
    }

    private function validateAccountId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->accountId) !== 1) {
            throw StaffMemberNotFound::forAccount($this->accountId);
        }
    }
}
