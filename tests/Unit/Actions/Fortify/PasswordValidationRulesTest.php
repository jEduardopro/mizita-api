<?php

declare(strict_types=1);

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
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
    expect(mizitaPasswordRules(confirmed: false))->not->toContain('confirmed');
});

it('requires a confirmation for the flows that still render one', function (array $rules) {
    expect($rules)->toContain('confirmed');
})->with([
    'called bare, as reset and update do' => fn () => mizitaPasswordRules(),
    'asked for explicitly' => fn () => mizitaPasswordRules(confirmed: true),
]);

it('keeps the strength requirements whether or not a confirmation is asked for', function (array $rules) {
    expect($rules)->toContain('required')->toContain('string');

    $password = array_values(array_filter($rules, fn (mixed $rule) => $rule instanceof Password));

    expect($password)->toHaveCount(1)
        ->and(iterator_to_array($password[0]))->toContain('min:8');
})->with([
    'without a confirmation' => fn () => mizitaPasswordRules(confirmed: false),
    'with a confirmation' => fn () => mizitaPasswordRules(),
]);
