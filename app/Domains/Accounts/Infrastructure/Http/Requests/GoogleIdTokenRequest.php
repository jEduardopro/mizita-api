<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Requests;

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
        ];
    }
}
