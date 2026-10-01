<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Requests;

use App\Domains\Payments\Application\Dtos\PaymentReportCriteriaInput;
use App\Domains\Payments\ValueObjects\LocalDate;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Illuminate\Validation\Rule;

final class PaymentReportCriteriaRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:'.LocalDate::FORMAT, 'required_with:to'],
            'to' => ['nullable', 'date_format:'.LocalDate::FORMAT, 'required_with:from', 'after_or_equal:from'],
            'customer_ids' => ['sometimes', 'array', 'max:'.PaymentReportCriteriaInput::MAXIMUM_CUSTOMER_FILTER_SIZE],
            'customer_ids.*' => ['uuid'],
            'direction' => ['sometimes', Rule::enum(SortDirection::class)],
            'page' => ['sometimes', 'integer', 'min:'.PaymentReportCriteriaInput::FIRST_PAGE],
            'per_page' => [
                'sometimes',
                'integer',
                'min:'.PaymentReportCriteriaInput::MINIMUM_PER_PAGE,
                'max:'.Pagination::MAXIMUM_PER_PAGE,
            ],
        ];
    }
}
