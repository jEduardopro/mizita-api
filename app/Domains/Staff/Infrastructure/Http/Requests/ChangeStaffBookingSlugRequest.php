<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Http\Requests;

use App\Domains\Staff\ValueObjects\BookingSlug;
use Illuminate\Foundation\Http\FormRequest;

final class ChangeStaffBookingSlugRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:'.BookingSlug::MAXIMUM_LENGTH,
                'regex:'.BookingSlug::SHAPE,
            ],
        ];
    }
}
