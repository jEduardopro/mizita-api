<?php

declare(strict_types=1);

use App\Domains\Platform\Application\Dtos\RegisterPlatformAdminInput;
use App\Domains\Platform\Entities\PlatformAdmin;
use App\Domains\Platform\Exceptions\InvalidPlatformAdminEmail;
use App\Domains\Platform\Exceptions\InvalidPlatformAdminName;
use App\Domains\Platform\Exceptions\InvalidPlatformTwoFactorCode;
use App\Domains\Platform\Exceptions\PlatformAdminPasswordTooShort;
use App\Shared\Contracts\DomainFailure;

/**
 * @param  array<string, string>  $overrides
 */
function registerPlatformAdminInput(array $overrides = []): RegisterPlatformAdminInput
{
    $fields = [
        'email' => 'grace@mizita.test',
        'name' => 'Grace Hopper',
        'password' => 'correct-horse-battery',
        'twoFactorSecret' => 'JBSWY3DPEHPK3PXP',
        'confirmationCode' => '123456',
        ...$overrides,
    ];

    return new RegisterPlatformAdminInput(...$fields);
}

it('accepts a well formed registration', function () {
    expect(fn () => registerPlatformAdminInput()->validate())->not->toThrow(Throwable::class);
});

it('requires twelve characters of password', function () {
    expect(RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH)->toBe(12);
});

it('accepts a password of exactly the minimum length', function (string $password) {
    expect(fn () => registerPlatformAdminInput(['password' => $password])->validate())->not->toThrow(Throwable::class);
})->with([
    'ascii' => [str_repeat('a', RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH)],
    'multibyte, counted in characters' => [str_repeat('ñ', RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH)],
]);

it('accepts a name of exactly the maximum length', function () {
    expect(fn () => registerPlatformAdminInput(['name' => str_repeat('é', PlatformAdmin::MAXIMUM_NAME_LENGTH)])->validate())
        ->not->toThrow(Throwable::class);
});

it('rejects a registration the console command would have refused', function (array $overrides, string $failure) {
    expect(fn () => registerPlatformAdminInput($overrides)->validate())->toThrow($failure);
})->with([
    'an empty email' => [['email' => ''], InvalidPlatformAdminEmail::class],
    'a blank email' => [['email' => '   '], InvalidPlatformAdminEmail::class],
    'a malformed email' => [['email' => 'grace.mizita.test'], InvalidPlatformAdminEmail::class],
    'an empty name' => [['name' => ''], InvalidPlatformAdminName::class],
    'a blank name' => [['name' => " \t "], InvalidPlatformAdminName::class],
    'a name past the maximum length' => [['name' => str_repeat('a', 256)], InvalidPlatformAdminName::class],
    'an empty password' => [['password' => ''], PlatformAdminPasswordTooShort::class],
    'a password one character short' => [['password' => str_repeat('a', 11)], PlatformAdminPasswordTooShort::class],
    'a multibyte password one character short, though long in bytes' => [['password' => str_repeat('ñ', 11)], PlatformAdminPasswordTooShort::class],
    'an empty code' => [['confirmationCode' => ''], InvalidPlatformTwoFactorCode::class],
    'a five digit code' => [['confirmationCode' => '12345'], InvalidPlatformTwoFactorCode::class],
    'a seven digit code' => [['confirmationCode' => '1234567'], InvalidPlatformTwoFactorCode::class],
    'letters' => [['confirmationCode' => 'abcdef'], InvalidPlatformTwoFactorCode::class],
    'a code with a space' => [['confirmationCode' => '123 456'], InvalidPlatformTwoFactorCode::class],
    'a code with a trailing newline' => [['confirmationCode' => "123456\n"], InvalidPlatformTwoFactorCode::class],
    'non ascii digits' => [['confirmationCode' => '١٢٣٤٥٦'], InvalidPlatformTwoFactorCode::class],
]);

it('refuses with a domain failure, never a PHP error', function (array $overrides) {
    try {
        registerPlatformAdminInput($overrides)->validate();
    } catch (DomainFailure $failure) {
        expect($failure)->toBeInstanceOf(Throwable::class);

        return;
    }

    test()->fail('An invalid registration was accepted.');
})->with([
    'email' => [['email' => '']],
    'name' => [['name' => '']],
    'password' => [['password' => '']],
    'code' => [['confirmationCode' => '']],
]);

it('reports the email first when everything is wrong', function () {
    expect(fn () => registerPlatformAdminInput([
        'email' => '',
        'name' => '',
        'password' => '',
        'confirmationCode' => '',
    ])->validate())->toThrow(InvalidPlatformAdminEmail::class);
});
