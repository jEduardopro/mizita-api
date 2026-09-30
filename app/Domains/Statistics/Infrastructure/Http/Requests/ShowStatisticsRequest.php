<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Infrastructure\Http\Requests;

use App\Domains\Statistics\ValueObjects\LocalDate;
use Illuminate\Foundation\Http\FormRequest;

final class ShowStatisticsRequest extends FormRequest
{
    private const DATE_FORMAT = 'date_format:'.LocalDate::FORMAT;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', self::DATE_FORMAT, 'required_with:to'],
            'to' => ['nullable', 'string', self::DATE_FORMAT, 'required_with:from', 'after_or_equal:from'],
        ];
    }
}
