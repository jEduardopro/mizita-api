<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The name comes from the query string, and is held to the same bounds the
 * create request applies: an answer about a name that could never be accepted
 * would be worse than useless.
 */
final class CheckBusinessNameAvailabilityRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }
}
