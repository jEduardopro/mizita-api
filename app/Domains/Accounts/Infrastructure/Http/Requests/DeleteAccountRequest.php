<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Requests;

use App\Domains\Accounts\Application\Dtos\DeleteAccountInput;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteAccountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'max:'.DeleteAccountInput::MAXIMUM_CONFIRMATION_LENGTH],
            'email' => ['nullable', 'string', 'max:'.DeleteAccountInput::MAXIMUM_CONFIRMATION_LENGTH],
        ];
    }
}
