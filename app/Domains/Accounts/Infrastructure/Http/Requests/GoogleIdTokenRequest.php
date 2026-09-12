<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GoogleIdTokenRequest extends FormRequest
{
    /**
     * Shape checking stops here. Whether the token is a genuine Google ID token
     * is a security rule enforced behind GoogleIdentityVerifier, so it holds
     * for every caller of that port rather than for this endpoint alone.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
        ];
    }
}
