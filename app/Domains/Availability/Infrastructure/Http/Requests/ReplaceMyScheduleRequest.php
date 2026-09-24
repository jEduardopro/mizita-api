<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Http\Requests;

use App\Domains\Availability\ValueObjects\Weekday;
use Illuminate\Foundation\Http\FormRequest;

final class ReplaceMyScheduleRequest extends FormRequest
{
    private const TIME_FORMAT = 'H:i';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'schedule' => ['present', 'array'],
            'schedule.*' => ['array'],
            'schedule.*.weekday' => [
                'required',
                'integer',
                'between:'.Weekday::Monday->value.','.Weekday::Sunday->value,
            ],
            'schedule.*.starts_at' => ['required', 'string', 'date_format:'.self::TIME_FORMAT],
            'schedule.*.ends_at' => ['required', 'string', 'date_format:'.self::TIME_FORMAT],
        ];
    }
}
