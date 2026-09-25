<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Requests;

use App\Domains\Staff\Application\Dtos\ListTeamMembersInput;
use App\Domains\Staff\ValueObjects\TeamSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListTeamMembersRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:'.ListTeamMembersInput::MAXIMUM_SEARCH_LENGTH],
            'sort' => ['sometimes', Rule::enum(TeamSort::class)],
            'direction' => ['sometimes', Rule::enum(SortDirection::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAXIMUM_PER_PAGE],
        ];
    }
}
