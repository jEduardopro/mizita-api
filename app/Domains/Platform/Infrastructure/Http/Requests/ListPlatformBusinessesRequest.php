<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Requests;

use App\Domains\Platform\Application\Dtos\ListPlatformBusinessesInput;
use App\Domains\Platform\ValueObjects\PlatformBusinessSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListPlatformBusinessesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:'.ListPlatformBusinessesInput::MAXIMUM_SEARCH_LENGTH],
            'sort' => ['sometimes', Rule::enum(PlatformBusinessSort::class)],
            'direction' => ['sometimes', Rule::enum(SortDirection::class)],
            'page' => ['sometimes', 'integer', 'min:'.ListPlatformBusinessesInput::FIRST_PAGE],
            'per_page' => [
                'sometimes',
                'integer',
                'min:'.ListPlatformBusinessesInput::MINIMUM_PER_PAGE,
                'max:'.Pagination::MAXIMUM_PER_PAGE,
            ],
        ];
    }
}
