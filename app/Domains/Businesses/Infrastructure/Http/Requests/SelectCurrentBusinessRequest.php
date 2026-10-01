<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SelectCurrentBusinessRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_id' => ['required', 'uuid'],
        ];
    }
}
