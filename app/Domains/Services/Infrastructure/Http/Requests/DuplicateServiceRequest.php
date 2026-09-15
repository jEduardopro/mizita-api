<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Http\Requests;

use App\Domains\Services\Entities\Service;
use Illuminate\Foundation\Http\FormRequest;

final class DuplicateServiceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'nullable',
                'string',
                'min:'.Service::MINIMUM_NAME_LENGTH,
                'max:'.Service::MAXIMUM_NAME_LENGTH,
            ],
        ];
    }
}
