<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Requests;

use App\Domains\Payments\Application\Dtos\CreateAppointmentPaymentInput;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateAppointmentPaymentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'add_ons' => ['sometimes', 'nullable', 'array', 'max:'.CreateAppointmentPaymentInput::MAXIMUM_ADD_ONS],
            'add_ons.*.name' => ['required', 'string', 'max:'.PaymentItemName::MAXIMUM_LENGTH],
            'add_ons.*.amount_cents' => ['required', 'integer', 'min:0', 'max:'.Money::MAXIMUM_CENTS],
            'discount' => ['sometimes', 'nullable', 'array'],
            'discount.type' => ['required_with:discount', Rule::enum(DiscountType::class)],
            'discount.value' => ['required_with:discount', 'integer', 'min:0'],
            'payment_method_id' => ['required', 'uuid'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:'.Money::MAXIMUM_CENTS],
        ];
    }
}
