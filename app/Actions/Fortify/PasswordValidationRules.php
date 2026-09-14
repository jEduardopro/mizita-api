<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * @param  bool  $confirmed  whether the screen asks the user to type the password twice
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(bool $confirmed = true): array
    {
        $rules = ['required', 'string', Password::default()];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
