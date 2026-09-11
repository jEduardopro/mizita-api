<?php

declare(strict_types=1);

/*
| Pins down the split the signup redesign introduced: registration is the one flow
| whose screen has no confirmation field - it offers a reveal toggle instead - while
| reset and update still render one and must keep requiring it.
|
| The trait is the only part of that decision reachable without the framework.
| CreateNewUser::create() goes through the Validator facade and User::create(), so
| what the rules then *do* is feature-test territory; what they *are* is asserted here.
|
| Password::default() constructs with no container behind it, which is what lets this
| stay in tests/Unit: nothing is booted, the rule objects are just inspected.
*/

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Calls the protected trait method through a throwaway consumer, exactly as the
 * Fortify actions consume it.
 *
 * The argument is variadic on purpose: passing none reproduces the bare
 * passwordRules() call in ResetUserPassword and UpdateUserPassword, so the
 * default is exercised rather than re-stated.
 *
 * @return array<int, Rule|array<mixed>|string>
 */
function mizitaPasswordRules(bool ...$confirmed): array
{
    $action = new class
    {
        use PasswordValidationRules;

        /**
         * @return array<int, Rule|array<mixed>|string>
         */
        public function rules(bool ...$confirmed): array
        {
            return $this->passwordRules(...$confirmed);
        }
    };

    return $action->rules(...$confirmed);
}

it('drops the confirmation rule for registration', function () {
    // The assertion the redesign rests on: the signup form posts no
    // password_confirmation, so a returning 'confirmed' fails every signup.
    expect(mizitaPasswordRules(confirmed: false))->not->toContain('confirmed');
});

it('requires a confirmation for the flows that still render one', function (array $rules) {
    expect($rules)->toContain('confirmed');
})->with([
    'called bare, as reset and update do' => fn () => mizitaPasswordRules(),
    'asked for explicitly' => fn () => mizitaPasswordRules(confirmed: true),
]);

it('keeps the strength requirements whether or not a confirmation is asked for', function (array $rules) {
    // Removing the confirmation was not meant to weaken anything else.
    expect($rules)->toContain('required')->toContain('string');

    $password = array_values(array_filter($rules, fn (mixed $rule) => $rule instanceof Password));

    expect($password)->toHaveCount(1)
        ->and(iterator_to_array($password[0]))->toContain('min:8');
})->with([
    'without a confirmation' => fn () => mizitaPasswordRules(confirmed: false),
    'with a confirmation' => fn () => mizitaPasswordRules(),
]);
