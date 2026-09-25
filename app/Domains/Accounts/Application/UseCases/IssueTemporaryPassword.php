<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\IssuedTemporaryPasswordData;
use App\Domains\Accounts\Application\Dtos\IssueTemporaryPasswordInput;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\PasswordHasher;
use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class IssueTemporaryPassword
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly TemporaryPasswordGenerator $temporaryPasswords,
        private readonly TemporaryPasswordVault $vault,
        private readonly PasswordHasher $hasher,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<IssuedTemporaryPasswordData>
     */
    public function handle(IssueTemporaryPasswordInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $temporaryPassword = $this->issue($input->accountId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(new IssuedTemporaryPasswordData($temporaryPassword));
    }

    /**
     * @throws AccountNotFound
     */
    private function issue(string $accountId): ?string
    {
        $account = $this->accounts->findById($accountId);

        if (! $account->acceptsTemporaryPassword()) {
            return null;
        }

        $temporaryPassword = $this->temporaryPasswords->generate();

        $account->issueTemporaryPassword($this->hasher->hash($temporaryPassword->value));

        $this->transactions->run(function () use ($account, $temporaryPassword): void {
            $this->accounts->save($account);
            $this->vault->keep($account->id, $temporaryPassword->value);
        });

        return $temporaryPassword->value;
    }
}
