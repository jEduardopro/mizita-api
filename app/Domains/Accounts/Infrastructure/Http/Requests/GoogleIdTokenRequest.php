<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Requests;

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use Illuminate\Foundation\Http\FormRequest;

final class GoogleIdTokenRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'code' => [
                'nullable',
                'string',
                'max:'.SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH,
                'prohibits:recovery_code',
            ],
            'recovery_code' => [
                'nullable',
                'string',
                'max:'.SignInWithGoogleIdTokenInput::MAXIMUM_RECOVERY_CODE_LENGTH,
                'prohibits:code',
            ],
        ];
    }
}
