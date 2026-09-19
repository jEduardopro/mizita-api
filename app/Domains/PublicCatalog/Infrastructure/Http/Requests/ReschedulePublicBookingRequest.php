<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReschedulePublicBookingRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'string', 'date'],
        ];
    }
}
