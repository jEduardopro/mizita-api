<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Application\Dtos;

use App\Domains\Addresses\Entities\State;

final readonly class StateData
{
    public function __construct(
        public string $id,
        public string $countryCode,
        public string $code,
        public string $name,
        public int $position,
    ) {}

    public static function fromEntity(State $state): self
    {
        return new self(
            id: $state->id,
            countryCode: $state->country()->value,
            code: $state->code(),
            name: $state->name(),
            position: $state->position(),
        );
    }
}
