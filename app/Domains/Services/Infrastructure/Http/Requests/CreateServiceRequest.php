<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Requests;

use App\Domains\Services\Entities\Service;
use App\Domains\Services\ValueObjects\Buffer;
use App\Domains\Services\ValueObjects\Duration;
use App\Domains\Services\ValueObjects\ServiceColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateServiceRequest extends FormRequest
{
    private const PRICE_SHAPE = 'regex:/^\d{1,8}(\.\d{1,2})?$/';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:'.Service::MINIMUM_NAME_LENGTH, 'max:'.Service::MAXIMUM_NAME_LENGTH],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.Service::MAXIMUM_DESCRIPTION_LENGTH],
            'duration_minutes' => ['required', 'integer', 'min:'.Duration::MINIMUM_MINUTES, 'max:'.Duration::MAXIMUM_MINUTES],
            'buffer_minutes' => ['required', 'integer', 'min:'.Buffer::MINIMUM_MINUTES, 'max:'.Buffer::MAXIMUM_MINUTES],
            'price' => ['required', 'string', self::PRICE_SHAPE],
            'color' => ['required', Rule::enum(ServiceColor::class)],
            'active' => ['required', 'boolean'],
            'staff_ids' => ['required', 'array', 'min:1', 'max:'.Service::MAXIMUM_STAFF_MEMBERS],
            'staff_ids.*' => ['uuid'],
        ];
    }
}
