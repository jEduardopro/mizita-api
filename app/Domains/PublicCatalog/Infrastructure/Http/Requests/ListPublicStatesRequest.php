<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListPublicStatesRequest extends FormRequest
{
    private const COUNTRY_CODE_LENGTH = 2;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'country' => ['sometimes', 'string', 'size:'.self::COUNTRY_CODE_LENGTH],
        ];
    }
}
