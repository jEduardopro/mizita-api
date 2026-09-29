<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartCheckoutRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'uuid'],
        ];
    }
}
