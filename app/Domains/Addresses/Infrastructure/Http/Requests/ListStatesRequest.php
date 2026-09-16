<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListStatesRequest extends FormRequest
{
    private const COUNTRY_CODE_LENGTH = 2;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'country' => ['sometimes', 'string', 'size:'.self::COUNTRY_CODE_LENGTH],
        ];
    }
}
