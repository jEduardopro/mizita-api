<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Requests;

use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\TransactionSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListTransactionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...PaymentReportCriteriaRules::rules(),
            'types' => ['sometimes', 'array'],
            'types.*' => [Rule::enum(PaymentTransactionType::class)],
            'methods' => ['sometimes', 'array'],
            'methods.*' => [Rule::enum(PaymentMethodCode::class)],
            'sort' => ['sometimes', Rule::enum(TransactionSort::class)],
        ];
    }
}
