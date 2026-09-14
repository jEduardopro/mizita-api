<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class LateValidatingUseCase
{
    /**
     * @return UseCaseResponse<string>
     */
    public function handle(ValidatableInput $input): UseCaseResponse
    {
        try {
            $name = trim($input->name);
            $input->validate();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($name);
    }
}
