<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Shared\Contracts\DomainFailure;

final class BareValueUseCase
{
    public function handle(ValidatableInput $input): string
    {
        try {
            $input->validate();
        } catch (DomainFailure $failure) {
            return $failure->errorCode();
        }

        return trim($input->name);
    }
}
