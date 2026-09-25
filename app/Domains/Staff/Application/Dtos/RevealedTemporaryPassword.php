<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use SensitiveParameter;

final readonly class RevealedTemporaryPassword
{
    public function __construct(
        #[SensitiveParameter]
        public string $temporaryPassword,
    ) {}
}
