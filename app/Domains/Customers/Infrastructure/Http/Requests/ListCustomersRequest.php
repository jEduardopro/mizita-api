<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Requests;

use App\Domains\Customers\Application\Dtos\ListCustomersInput;
use App\Domains\Customers\ValueObjects\CustomerSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCustomersRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:'.ListCustomersInput::MAXIMUM_SEARCH_LENGTH],
            'sort' => ['sometimes', Rule::enum(CustomerSort::class)],
            'direction' => ['sometimes', Rule::enum(SortDirection::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAXIMUM_PER_PAGE],
        ];
    }
}
