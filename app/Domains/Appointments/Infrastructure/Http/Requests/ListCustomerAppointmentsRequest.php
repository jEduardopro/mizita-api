<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Http\Requests;

use App\Shared\ValueObjects\Pagination;
use Illuminate\Foundation\Http\FormRequest;

final class ListCustomerAppointmentsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAXIMUM_PER_PAGE],
        ];
    }
}
