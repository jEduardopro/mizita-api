<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Requests;

use App\Domains\Staff\Application\Dtos\ProfilePhoneInput;
use App\Domains\Staff\Application\Dtos\UpdateMyProfileInput;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMyProfileRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.UpdateMyProfileInput::MAXIMUM_NAME_LENGTH],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:'.JobTitle::MAXIMUM_LENGTH],
            'about' => ['sometimes', 'nullable', 'string', 'max:'.About::MAXIMUM_LENGTH],
            ...$this->phoneRules(),
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
                'size:'.ProfilePhoneInput::COUNTRY_CODE_LENGTH,
            ],
            'phone.national_number' => [
                'required_with:phone',
                'string',
                'max:'.ProfilePhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH,
            ],
        ];
    }
}
