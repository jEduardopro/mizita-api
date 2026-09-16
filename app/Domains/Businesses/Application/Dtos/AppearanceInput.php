<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

final readonly class AppearanceInput
{
    public function __construct(
        public string $accentColor,
        public string $buttonShape,
        public string $theme,
    ) {}
}
