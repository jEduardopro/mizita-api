<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Http\Requests;

use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use Illuminate\Foundation\Http\FormRequest;

final class CreateAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
            'staff_member_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'string', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'string', 'date', 'after:starts_at'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.AppointmentNotes::MAXIMUM_LENGTH],
        ];
    }
}
