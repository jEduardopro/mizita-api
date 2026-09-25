<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\IssueTemporaryPasswordInput;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('accepts a uuid, whatever its case', function (string $accountId) {
    expect(fn () => (new IssueTemporaryPasswordInput($accountId))->validate())->not->toThrow(Throwable::class);
})->with([
    'lowercase' => '01930000-0000-7000-8000-0000000000c1',
    'uppercase' => '01930000-0000-7000-8000-0000000000C1',
]);

it('answers a malformed id as an account that does not exist', function (string $accountId) {
    expect(fn () => (new IssueTemporaryPasswordInput($accountId))->validate())
        ->toThrow(AccountNotFound::class, "Account [{$accountId}] was not found.");
})->with([
    'empty' => '',
    'whitespace' => '   ',
    'an internal integer key' => '7',
    'not a uuid' => 'account-uuid',
    'no hyphens' => '019300000000700080000000000000c1',
    'one character short' => '01930000-0000-7000-8000-0000000000c',
    'one character long' => '01930000-0000-7000-8000-0000000000c1a',
    'not hexadecimal' => '01930000-0000-7000-8000-0000000000zz',
    'a trailing newline' => "01930000-0000-7000-8000-0000000000c1\n",
    'surrounding spaces' => ' 01930000-0000-7000-8000-0000000000c1 ',
]);

it('refuses with a not found domain failure, so a probe learns nothing more than a missing account would tell it', function () {
    try {
        (new IssueTemporaryPasswordInput('not-a-uuid'))->validate();
    } catch (AccountNotFound $refusal) {
        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('account_not_found')
            ->and($refusal->kind())->toBe(DomainFailureKind::NotFound);

        return;
    }

    throw new RuntimeException('The malformed id was accepted.');
});
