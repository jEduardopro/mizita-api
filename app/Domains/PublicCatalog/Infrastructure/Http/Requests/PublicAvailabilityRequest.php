<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublicAvailabilityRequest extends FormRequest
{
    private const DATE_FORMAT = 'date_format:Y-m-d';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid'],
            'staff_id' => ['required', 'uuid'],
            'from' => ['required', 'string', self::DATE_FORMAT],
            'to' => ['required', 'string', self::DATE_FORMAT],
        ];
    }
}
