<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Requests;

use App\Domains\Staff\Application\Dtos\ProfilePhoneInput;
use App\Domains\Staff\Application\Dtos\UpdateTeamMemberInput;
use App\Domains\Staff\ValueObjects\About;
use App\Domains\Staff\ValueObjects\JobTitle;
use App\Domains\Staff\ValueObjects\StaffRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.UpdateTeamMemberInput::MAXIMUM_NAME_LENGTH],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:'.JobTitle::MAXIMUM_LENGTH],
            'about' => ['sometimes', 'nullable', 'string', 'max:'.About::MAXIMUM_LENGTH],
            'level' => ['sometimes', 'required', 'string', Rule::in(StaffRole::assignableValues())],
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
