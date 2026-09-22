<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Requests;

use App\Domains\Payments\ValueObjects\Money;
use Illuminate\Foundation\Http\FormRequest;

final class RecordPaymentTransactionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'uuid'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:'.Money::MAXIMUM_CENTS],
        ];
    }
}
