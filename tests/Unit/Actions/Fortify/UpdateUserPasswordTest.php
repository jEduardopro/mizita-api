<?php

declare(strict_types=1);

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

const CURRENT_PASSWORD = 'Current1!Pass';

const STRONG_PASSWORD = 'Str0ng!Pass';

const UPDATE_PASSWORD_BAG = 'updatePassword';

function refusedPasswordUpdate(User $user, array $input): ValidationException
{
    try {
        (new UpdateUserPassword)->update($user, $input);
    } catch (ValidationException $refusal) {
        return $refusal;
    }

    throw new RuntimeException('The password update was not refused.');
}

beforeEach(function () {
    $this->passwordAccount = (new User)->forceFill(['password' => CURRENT_PASSWORD]);
    $this->googleAccount = new User;
});

describe('an account that signs in with a password', function () {
    it('requires the current password', function () {
        $refusal = refusedPasswordUpdate($this->passwordAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect(array_keys($refusal->errors()))->toBe(['current_password']);
    });

    it('rejects a current password that does not match the stored one', function () {
        $this->actingAs($this->passwordAccount, 'web');

        $refusal = refusedPasswordUpdate($this->passwordAccount, [
            'current_password' => 'Wr0ng!Pass',
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect(array_keys($refusal->errors()))->toBe(['current_password']);
    });

    it('leaves the stored password untouched when the update is refused', function () {
        $storedHash = $this->passwordAccount->getAuthPassword();

        refusedPasswordUpdate($this->passwordAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect($this->passwordAccount->getAuthPassword())->toBe($storedHash)
            ->and($this->passwordAccount->exists)->toBeFalse();
    });
});

describe('an account created through Google, with no password yet', function () {
    it('does not ask for a current password it never had', function () {
        $refusal = refusedPasswordUpdate($this->googleAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Different1!Pass',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });

    it('ignores a current password it was sent anyway', function () {
        $refusal = refusedPasswordUpdate($this->googleAccount, [
            'current_password' => 'anything at all',
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Different1!Pass',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });
});

describe('the new password', function () {
    it('rejects a password that misses the strength rule', function (string $weak) {
        $refusal = refusedPasswordUpdate($this->googleAccount, [
            'password' => $weak,
            'password_confirmation' => $weak,
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    })->with([
        'the old factory default' => 'password',
        'shorter than eight characters' => 'Sh0rt!x',
        'no uppercase letter' => 'str0ng!pass',
        'no lowercase letter' => 'STR0NG!PASS',
        'no number' => 'Strong!Pass',
        'no symbol' => 'Str0ngPass',
        'blank' => '',
    ]);

    it('rejects a password whose confirmation does not match', function () {
        $refusal = refusedPasswordUpdate($this->googleAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Str0ng!Pas',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });

    it('rejects a missing password', function () {
        $refusal = refusedPasswordUpdate($this->googleAccount, []);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });
});

describe('where the refusal is reported', function () {
    it('reports every refusal under the update password error bag', function (string $account, array $input) {
        expect(refusedPasswordUpdate($this->{$account}, $input)->errorBag)->toBe(UPDATE_PASSWORD_BAG);
    })->with([
        'a missing current password' => ['passwordAccount', ['password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD]],
        'a weak new password' => ['googleAccount', ['password' => 'password', 'password_confirmation' => 'password']],
    ]);
});
