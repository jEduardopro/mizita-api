<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Requests;

use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\ValueObjects\BirthDate;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCustomerRequest extends FormRequest
{
    private const COUNTRY_CODE_LENGTH = 2;

    private const MAXIMUM_STREET_LENGTH = 160;

    private const MAXIMUM_CITY_LENGTH = 120;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.Customer::MAXIMUM_NAME_LENGTH],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:'.CustomerEmail::MAXIMUM_LENGTH],
            'birth_date' => ['sometimes', 'nullable', 'date_format:'.BirthDate::FORMAT],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.Customer::MAXIMUM_NOTES_LENGTH],
            ...$this->phoneRules(),
            ...$this->addressRules(),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function phoneRules(): array
    {
        return [
            'phone' => ['sometimes', 'nullable', 'array'],
            'phone.country_code' => [
                'required_with:phone',
                'string',
                'size:'.CustomerPhoneInput::COUNTRY_CODE_LENGTH,
            ],
            'phone.national_number' => [
                'required_with:phone',
                'string',
                'max:'.CustomerPhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH,
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function addressRules(): array
    {
        return [
            'address' => ['sometimes', 'nullable', 'array'],
            'address.street' => ['sometimes', 'nullable', 'string', 'max:'.self::MAXIMUM_STREET_LENGTH],
            'address.city' => ['sometimes', 'nullable', 'string', 'max:'.self::MAXIMUM_CITY_LENGTH],
            'address.state_id' => ['sometimes', 'nullable', 'uuid'],
            'address.postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'min:'.PostalCode::MINIMUM_DIGITS,
                'max:'.PostalCode::MAXIMUM_DIGITS,
            ],
            'address.country_code' => [
                'required_with:address',
                'string',
                'size:'.self::COUNTRY_CODE_LENGTH,
            ],
        ];
    }
}
