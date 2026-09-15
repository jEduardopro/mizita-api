<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Requests;

use App\Domains\Services\Application\Dtos\ListServicesInput;
use App\Domains\Services\ValueObjects\ServiceSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListServicesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:'.ListServicesInput::MAXIMUM_SEARCH_LENGTH],
            'sort' => ['sometimes', Rule::enum(ServiceSort::class)],
            'direction' => ['sometimes', Rule::enum(SortDirection::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAXIMUM_PER_PAGE],
        ];
    }
}
