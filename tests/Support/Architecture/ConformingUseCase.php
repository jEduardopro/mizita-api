<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ConformingUseCase
{
    /**
     * @return UseCaseResponse<string>
     */
    public function handle(ValidatableInput $input): UseCaseResponse
    {
        try {
            $input->validate();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(trim($input->name));
    }
}
