<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class UnvalidatedInput
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self;
    }
}
