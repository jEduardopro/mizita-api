<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Requests;

use App\Domains\PublicCatalog\ValueObjects\PublicBookingRequest;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestDetails;
use Illuminate\Foundation\Http\FormRequest;

final class BookPublicAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid'],
            'staff_member_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'string', 'date'],
            'guest' => ['required', 'array'],
            'guest.name' => ['required', 'string', 'max:'.PublicGuestDetails::MAXIMUM_NAME_LENGTH],
            'guest.email' => ['sometimes', 'nullable', 'string', 'email', 'max:'.PublicGuestDetails::MAXIMUM_EMAIL_LENGTH],
            'guest.phone' => ['sometimes', 'nullable', 'array'],
            'guest.phone.country_code' => ['required_with:guest.phone', 'string', 'size:'.PublicGuestDetails::COUNTRY_CODE_LENGTH],
            'guest.phone.national_number' => ['required_with:guest.phone', 'string', 'max:'.PublicGuestDetails::MAXIMUM_NATIONAL_NUMBER_LENGTH],
            'guest.address' => ['sometimes', 'nullable', 'array'],
            'guest.address.street' => ['sometimes', 'nullable', 'string', 'max:'.PublicGuestAddress::MAXIMUM_STREET_LENGTH],
            'guest.address.city' => ['sometimes', 'nullable', 'string', 'max:'.PublicGuestAddress::MAXIMUM_CITY_LENGTH],
            'guest.address.state' => ['sometimes', 'nullable', 'string', 'max:'.PublicGuestAddress::MAXIMUM_STATE_LENGTH],
            'guest.address.postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'min:'.PublicGuestAddress::MINIMUM_POSTAL_CODE_LENGTH,
                'max:'.PublicGuestAddress::MAXIMUM_POSTAL_CODE_LENGTH,
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.PublicBookingRequest::MAXIMUM_NOTES_LENGTH],
        ];
    }
}
