<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Requests;

use App\Domains\Payments\Application\Dtos\ListSalesInput;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\SaleSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSalesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...PaymentReportCriteriaRules::rules(),
            'reference' => ['sometimes', 'nullable', 'string', 'max:'.ListSalesInput::MAXIMUM_REFERENCE_LENGTH],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [Rule::enum(PaymentStatus::class)],
            'sort' => ['sometimes', Rule::enum(SaleSort::class)],
        ];
    }
}
