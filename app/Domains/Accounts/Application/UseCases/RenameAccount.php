<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\RenameAccountInput;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class RenameAccount
{
    public function __construct(
        private readonly AccountRepository $accounts,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(RenameAccountInput $input): UseCaseResponse
    {
        try {
            $account = $this->accounts->findById($input->accountId);

            $account->rename($input->name);

            $this->accounts->save($account);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }
}
