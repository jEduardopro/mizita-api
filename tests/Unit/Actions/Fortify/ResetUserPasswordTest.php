<?php

declare(strict_types=1);

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

const RESET_STRONG_PASSWORD = 'Str0ng!Pass';

const RESET_TEMPORARY_PASSWORD = 'Tq7mW2xK9pLr4ZvB8nYd';

beforeEach(function () {
    $this->account = (new User)->forceFill([
        'password' => 'Current1!Pass',
        'must_change_password' => true,
        'temporary_password' => RESET_TEMPORARY_PASSWORD,
    ]);

    $this->savedAttributes = null;

    User::saving(function (User $account): bool {
        $this->savedAttributes = $account->getAttributes();

        return false;
    });
});

describe('a reset that is accepted', function () {
    beforeEach(function () {
        (new ResetUserPassword)->reset($this->account, [
            'password' => RESET_STRONG_PASSWORD,
            'password_confirmation' => RESET_STRONG_PASSWORD,
        ]);
    });

    it('discards the temporary password an owner could still copy', function () {
        expect($this->savedAttributes)->toHaveKey('temporary_password')
            ->and($this->savedAttributes['temporary_password'])->toBeNull();
    });

    it('stops asking for a password change', function () {
        expect($this->savedAttributes['must_change_password'])->toBeFalse();
    });
});

it('keeps the temporary password when the reset is refused', function () {
    expect(fn () => (new ResetUserPassword)->reset($this->account, [
        'password' => 'password',
        'password_confirmation' => 'password',
    ]))->toThrow(ValidationException::class);

    expect($this->savedAttributes)->toBeNull()
        ->and($this->account->temporary_password)->toBe(RESET_TEMPORARY_PASSWORD);
});
