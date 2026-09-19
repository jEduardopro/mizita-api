<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Requests;

use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Domains\BookingPolicies\ValueObjects\PolicyMessage;
use App\Domains\Businesses\Application\Dtos\BrandDetailsInput;
use App\Domains\Businesses\Application\Dtos\LocationInput;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBusinessSettingsRequest extends FormRequest
{
    private const COUNTRY_CODE_LENGTH = 2;

    private const CURRENCY_CODE_LENGTH = 3;

    private const MAXIMUM_NATIONAL_NUMBER_LENGTH = 24;

    private const MAXIMUM_STREET_LENGTH = 160;

    private const MAXIMUM_CITY_LENGTH = 120;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->brandRules(),
            ...$this->appearanceRules(),
            ...$this->bookingPolicyRules(),
            ...$this->contactRules(),
            ...$this->locationRules(),
            ...$this->scheduleRules(),
            ...$this->linkRules(),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function brandRules(): array
    {
        return [
            'brand' => ['sometimes', 'array'],
            'brand.name' => [
                'required_with:brand',
                'string',
                'min:'.BrandDetailsInput::MINIMUM_NAME_LENGTH,
                'max:'.BrandDetailsInput::MAXIMUM_NAME_LENGTH,
            ],
            'brand.slug' => ['required_with:brand', 'string'],
            'brand.industry_id' => ['required_with:brand', 'uuid'],
            'brand.about' => ['sometimes', 'nullable', 'string', 'max:'.About::MAXIMUM_LENGTH],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function appearanceRules(): array
    {
        return [
            'appearance' => ['sometimes', 'array'],
            'appearance.accent_color' => ['required_with:appearance', 'string'],
            'appearance.button_shape' => ['required_with:appearance', 'string'],
            'appearance.theme' => ['required_with:appearance', 'string'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function bookingPolicyRules(): array
    {
        return [
            'booking_policy' => ['sometimes', 'array'],
            'booking_policy.lead_time_minutes' => ['required_with:booking_policy', 'integer'],
            'booking_policy.booking_window_minutes' => ['present_with:booking_policy', 'nullable', 'integer'],
            'booking_policy.slot_granularity_minutes' => ['required_with:booking_policy', 'integer'],
            'booking_policy.cancellation_window_minutes' => ['present_with:booking_policy', 'nullable', 'integer'],
            'booking_policy.policy_message' => [
                'present_with:booking_policy',
                'nullable',
                'string',
                'max:'.PolicyMessage::MAXIMUM_LENGTH,
            ],
            'booking_policy.display_on_booking_page' => ['required_with:booking_policy', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function contactRules(): array
    {
        return [
            'contact' => ['sometimes', 'array'],
            'contact.contact_email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:'.ContactEmail::MAXIMUM_LENGTH,
            ],
            'contact.phone' => ['sometimes', 'nullable', 'array'],
            'contact.phone.country_code' => [
                'required_with:contact.phone',
                'string',
                'size:'.self::COUNTRY_CODE_LENGTH,
            ],
            'contact.phone.national_number' => [
                'required_with:contact.phone',
                'string',
                'max:'.self::MAXIMUM_NATIONAL_NUMBER_LENGTH,
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function locationRules(): array
    {
        return [
            'location' => ['sometimes', 'array'],
            'location.street' => ['sometimes', 'nullable', 'string', 'max:'.self::MAXIMUM_STREET_LENGTH],
            'location.city' => ['sometimes', 'nullable', 'string', 'max:'.self::MAXIMUM_CITY_LENGTH],
            'location.state_id' => ['sometimes', 'nullable', 'uuid'],
            'location.postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'min:'.PostalCode::MINIMUM_DIGITS,
                'max:'.PostalCode::MAXIMUM_DIGITS,
            ],
            'location.country_code' => [
                'required_with:location',
                'string',
                'size:'.self::COUNTRY_CODE_LENGTH,
            ],
            'location.latitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:'.LocationInput::MINIMUM_LATITUDE.','.LocationInput::MAXIMUM_LATITUDE,
            ],
            'location.longitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:'.LocationInput::MINIMUM_LONGITUDE.','.LocationInput::MAXIMUM_LONGITUDE,
            ],
            'location.currency_code' => [
                'required_with:location',
                'string',
                'size:'.self::CURRENCY_CODE_LENGTH,
            ],
            'location.timezone' => ['required_with:location', 'string', 'timezone:all_with_bc'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function scheduleRules(): array
    {
        return [
            'schedule' => ['sometimes', 'array'],
            'schedule.*.weekday' => ['required', 'integer'],
            'schedule.*.starts_at' => ['required', 'string'],
            'schedule.*.ends_at' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function linkRules(): array
    {
        return [
            'links' => ['sometimes', 'array'],
            'links.*.platform' => ['required', 'string'],
            'links.*.url' => ['required', 'string'],
        ];
    }
}
