<?php

declare(strict_types=1);

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\Support\Accounts\FakeAccountSessions;
use Tests\TestCase;

uses(TestCase::class);

const CURRENT_PASSWORD = 'Current1!Pass';

const STRONG_PASSWORD = 'Str0ng!Pass';

const UPDATE_PASSWORD_BAG = 'updatePassword';

const UPDATE_TEMPORARY_PASSWORD = 'Tq7mW2xK9pLr4ZvB8nYd';

const STOPPED_AFTER_SAVING = 'The update was stopped once the account was about to be saved.';

const UPDATE_PASSWORD_ACCOUNT_UUID = '01930000-0000-7000-8000-00000000ac09';

function refusedPasswordUpdate(UpdateUserPassword $action, User $user, array $input): ValidationException
{
    try {
        $action->update($user, $input);
    } catch (ValidationException $refusal) {
        return $refusal;
    }

    throw new RuntimeException('The password update was not refused.');
}

beforeEach(function () {
    $this->sessions = new FakeAccountSessions;
    $this->action = new UpdateUserPassword($this->sessions);
    $this->passwordAccount = (new User)->forceFill(['password' => CURRENT_PASSWORD]);
    $this->googleAccount = new User;
});

describe('an account that signs in with a password', function () {
    it('requires the current password', function () {
        $refusal = refusedPasswordUpdate($this->action, $this->passwordAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect(array_keys($refusal->errors()))->toBe(['current_password']);
    });

    it('rejects a current password that does not match the stored one', function () {
        $this->actingAs($this->passwordAccount, 'web');

        $refusal = refusedPasswordUpdate($this->action, $this->passwordAccount, [
            'current_password' => 'Wr0ng!Pass',
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect(array_keys($refusal->errors()))->toBe(['current_password']);
    });

    it('leaves the stored password untouched when the update is refused', function () {
        $storedHash = $this->passwordAccount->getAuthPassword();

        refusedPasswordUpdate($this->action, $this->passwordAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect($this->passwordAccount->getAuthPassword())->toBe($storedHash)
            ->and($this->passwordAccount->exists)->toBeFalse();
    });
});

describe('an account created through Google, with no password yet', function () {
    it('does not ask for a current password it never had', function () {
        $refusal = refusedPasswordUpdate($this->action, $this->googleAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Different1!Pass',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });

    it('ignores a current password it was sent anyway', function () {
        $refusal = refusedPasswordUpdate($this->action, $this->googleAccount, [
            'current_password' => 'anything at all',
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Different1!Pass',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });
});

describe('an account still holding the temporary password it was issued', function () {
    beforeEach(function () {
        $this->temporaryAccount = (new User)->forceFill([
            'password' => CURRENT_PASSWORD,
            'must_change_password' => true,
        ]);
    });

    it('does not ask for the temporary password it was handed', function () {
        $refusal = refusedPasswordUpdate($this->action, $this->temporaryAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Different1!Pass',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });

    it('ignores a current password it was sent anyway, even a wrong one', function () {
        $refusal = refusedPasswordUpdate($this->action, $this->temporaryAccount, [
            'current_password' => 'Wr0ng!Pass',
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Different1!Pass',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });

    it('still holds the new password to the strength rule', function () {
        $refusal = refusedPasswordUpdate($this->action, $this->temporaryAccount, [
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password'])
            ->and($refusal->errorBag)->toBe(UPDATE_PASSWORD_BAG);
    });

    it('asks for the current password again once the flag is cleared', function () {
        $this->temporaryAccount->forceFill(['must_change_password' => false]);

        $refusal = refusedPasswordUpdate($this->action, $this->temporaryAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect(array_keys($refusal->errors()))->toBe(['current_password']);
    });
});

describe('the new password', function () {
    it('rejects a password that misses the strength rule', function (string $weak) {
        $refusal = refusedPasswordUpdate($this->action, $this->googleAccount, [
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
        $refusal = refusedPasswordUpdate($this->action, $this->googleAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => 'Str0ng!Pas',
        ]);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });

    it('rejects a missing password', function () {
        $refusal = refusedPasswordUpdate($this->action, $this->googleAccount, []);

        expect(array_keys($refusal->errors()))->toBe(['password']);
    });
});

describe('where the refusal is reported', function () {
    it('reports every refusal under the update password error bag', function (string $account, array $input) {
        expect(refusedPasswordUpdate($this->action, $this->{$account}, $input)->errorBag)->toBe(UPDATE_PASSWORD_BAG);
    })->with([
        'a missing current password' => ['passwordAccount', ['password' => STRONG_PASSWORD, 'password_confirmation' => STRONG_PASSWORD]],
        'a weak new password' => ['googleAccount', ['password' => 'password', 'password_confirmation' => 'password']],
    ]);
});

describe('an update that is accepted', function () {
    beforeEach(function () {
        $this->temporaryAccount = (new User)->forceFill([
            'password' => CURRENT_PASSWORD,
            'must_change_password' => true,
        ]);

        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (Closure $work): mixed => $work());

        $this->savedAttributes = null;

        User::saving(function (User $account): never {
            $this->savedAttributes = $account->getAttributes();

            throw new RuntimeException(STOPPED_AFTER_SAVING);
        });
    });

    it('discards the temporary password an owner could still copy', function (string $account) {
        $this->{$account}->forceFill(['temporary_password' => UPDATE_TEMPORARY_PASSWORD]);

        expect(fn () => $this->action->update($this->{$account}, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]))
            ->toThrow(RuntimeException::class, STOPPED_AFTER_SAVING);

        expect($this->savedAttributes)->toHaveKey('temporary_password')
            ->and($this->savedAttributes['temporary_password'])->toBeNull()
            ->and($this->savedAttributes['must_change_password'])->toBeFalse();
    })->with([
        'still holding the temporary password' => 'temporaryAccount',
        'created through Google' => 'googleAccount',
    ]);

    it('ends no session when the save fails', function () {
        expect(fn () => $this->action->update($this->temporaryAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]))->toThrow(RuntimeException::class, STOPPED_AFTER_SAVING)
            ->and($this->sessions->endedExcept)->toBe([])
            ->and($this->sessions->endedForAll)->toBe([]);
    });
});

describe('the other sessions of the account', function () {
    it('ends none when the update is refused', function () {
        refusedPasswordUpdate($this->action, $this->passwordAccount, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect($this->sessions->endedExcept)->toBe([])
            ->and($this->sessions->endedForAll)->toBe([]);
    });

    it('ends every other session of the account by its uuid, keeping the current one', function () {
        $account = (new User)->forceFill(['uuid' => UPDATE_PASSWORD_ACCOUNT_UUID]);
        DB::shouldReceive('transaction')->once()->andReturnUsing(fn (Closure $work): mixed => $work());
        User::saving(fn (): bool => false);

        $this->action->update($account, [
            'password' => STRONG_PASSWORD,
            'password_confirmation' => STRONG_PASSWORD,
        ]);

        expect($this->sessions->endedExcept)->toBe([[
            'accountId' => UPDATE_PASSWORD_ACCOUNT_UUID,
            'keptSessionId' => Session::getId(),
        ]])
            ->and($this->sessions->endedForAll)->toBe([]);
    });
});
