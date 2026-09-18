<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListAppointmentsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'date'],
            'to' => ['required', 'string', 'date', 'after:from'],
        ];
    }
}
