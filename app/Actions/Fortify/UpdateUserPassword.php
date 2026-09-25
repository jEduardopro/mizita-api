<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    private const DATABASE_SESSION_DRIVER = 'database';

    private const SESSION_PASSWORD_HASH_PREFIX = 'password_hash_';

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            ...$this->currentPasswordRules($user),
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'password' => Hash::make($input['password']),
                'must_change_password' => false,
            ])->save();

            $user->tokens()->delete();

            $this->endOtherSessionsOf($user);
        });

        $this->keepCurrentSessionSignedIn($user);
    }

    /**
     * @return array<string, list<string>>
     */
    private function currentPasswordRules(User $user): array
    {
        if ($user->getAuthPassword() === null || $user->mustChangePassword()) {
            return [];
        }

        return ['current_password' => ['required', 'string', 'current_password:web']];
    }

    private function endOtherSessionsOf(User $user): void
    {
        if (config('session.driver') !== self::DATABASE_SESSION_DRIVER) {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', Session::getId())
            ->delete();
    }

    private function keepCurrentSessionSignedIn(User $user): void
    {
        foreach (Arr::wrap(config('sanctum.guard')) as $guardName) {
            $guard = Auth::guard($guardName);

            if (! $guard instanceof SessionGuard) {
                continue;
            }

            Session::put(
                self::SESSION_PASSWORD_HASH_PREFIX.$guardName,
                $guard->hashPasswordForCookie($user->getAuthPassword()),
            );
        }
    }
}
