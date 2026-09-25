<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $this->notScheduledForDeletion(...),
                Rule::unique(User::class)->withoutTrashed(),
            ],
            'password' => $this->passwordRules(confirmed: false),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }

    private function notScheduledForDeletion(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $scheduledForDeletion = User::onlyTrashed()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($value))])
            ->exists();

        if ($scheduledForDeletion) {
            $fail(__('auth.scheduled_for_deletion'));
        }
    }
}
