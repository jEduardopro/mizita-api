<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Exceptions\AccountNotFound;

final readonly class PreviewAccountDeletionInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $accountId,
    ) {}

    /**
     * @throws AccountNotFound
     */
    public function validate(): void
    {
        $this->validateAccountId();
    }

    private function validateAccountId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->accountId) !== 1) {
            throw AccountNotFound::withId($this->accountId);
        }
    }
}
