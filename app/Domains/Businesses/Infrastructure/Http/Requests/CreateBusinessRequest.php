<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateBusinessRequest extends FormRequest
{
    private const COUNTRY_CODE_LENGTH = 2;

    private const MAX_NATIONAL_NUMBER_LENGTH = 24;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'timezone' => ['required', 'string', 'timezone:all_with_bc'],
            'industry_id' => ['required', 'uuid'],
            'phone' => ['sometimes', 'nullable', 'array'],
            'phone.country_code' => [
                'required_with:phone',
                'string',
                'size:'.self::COUNTRY_CODE_LENGTH,
            ],
            'phone.national_number' => [
                'required_with:phone',
                'string',
                'max:'.self::MAX_NATIONAL_NUMBER_LENGTH,
            ],
        ];
    }
}
