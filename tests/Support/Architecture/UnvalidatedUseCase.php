<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Shared\Application\UseCaseResponse;

final class UnvalidatedUseCase
{
    /**
     * @return UseCaseResponse<string>
     */
    public function handle(ValidatableInput $input): UseCaseResponse
    {
        return UseCaseResponse::success(trim($input->name));
    }
}
