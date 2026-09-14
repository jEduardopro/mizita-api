<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class ValidatableInput
{
    public function __construct(public string $name = '') {}

    public function validate(): void {}
}
