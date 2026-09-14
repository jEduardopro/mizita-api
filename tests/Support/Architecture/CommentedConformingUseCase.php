<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class CommentedConformingUseCase
{
    public function handle(ValidatableInput $input): string
    {
        // A banner the backend has not stripped yet must not hide the call.
        /*
         | Nor must a block one.
         */
        $input->validate();

        return trim($input->name);
    }
}
