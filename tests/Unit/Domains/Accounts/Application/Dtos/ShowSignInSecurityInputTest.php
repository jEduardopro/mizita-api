<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\ShowSignInSecurityInput;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\SignInSecurityFixtures;

it('accepts the uuid of the signed-in account', function (string $accountId) {
    $input = new ShowSignInSecurityInput($accountId);

    expect($input->accountId)->toBe($accountId)
        ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
})->with([
    'lower case' => SignInSecurityFixtures::ACCOUNT_ID,
    'upper case' => strtoupper(SignInSecurityFixtures::ACCOUNT_ID),
]);

it('refuses anything but a uuid as an account that does not exist', function (string $accountId) {
    expect(fn () => (new ShowSignInSecurityInput($accountId))->validate())
        ->toThrow(AccountNotFound::class, "Account [{$accountId}] was not found.");
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'an internal integer key' => '7',
    'garbage' => 'not-a-uuid',
    'a uuid with a trailing newline' => SignInSecurityFixtures::ACCOUNT_ID."\n",
    'a uuid wrapped in braces' => '{'.SignInSecurityFixtures::ACCOUNT_ID.'}',
    'a uuid without dashes' => str_replace('-', '', SignInSecurityFixtures::ACCOUNT_ID),
    'a uuid one character short' => substr(SignInSecurityFixtures::ACCOUNT_ID, 0, -1),
]);

it('classifies the refusal as not found with a stable code', function () {
    $refusal = null;

    try {
        (new ShowSignInSecurityInput('not-a-uuid'))->validate();
    } catch (AccountNotFound $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal->errorCode())->toBe('account_not_found')
        ->and($refusal->kind())->toBe(DomainFailureKind::NotFound);
});
