<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class CommentedConformingUseCase
{
    /**
     * @return UseCaseResponse<string>
     */
    public function handle(ValidatableInput $input): UseCaseResponse
    {
        // A banner the backend has not stripped yet must not hide the try.
        /*
         | Nor must a block one.
         */
        try {
            // And neither must one sitting between the try and the call.
            $input->validate();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(trim($input->name));
    }
}
