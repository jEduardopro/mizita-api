<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

final readonly class IssuedTemporaryPasswordData
{
    public function __construct(
        public ?string $temporaryPassword,
    ) {}
}
