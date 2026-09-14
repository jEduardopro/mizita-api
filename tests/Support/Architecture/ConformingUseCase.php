<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class ConformingUseCase
{
    public function handle(ValidatableInput $input): string
    {
        $input->validate();

        return trim($input->name);
    }
}
