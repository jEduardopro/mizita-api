<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface CurrentBusinessResolver
{
    /**
     * @throws DomainFailure
     */
    public function resolveFor(string $accountId, ?string $requestedBusinessId): string;
}
