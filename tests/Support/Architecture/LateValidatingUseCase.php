<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class LateValidatingUseCase
{
    public function handle(ValidatableInput $input): string
    {
        $name = trim($input->name);

        $input->validate();

        return $name;
    }
}
