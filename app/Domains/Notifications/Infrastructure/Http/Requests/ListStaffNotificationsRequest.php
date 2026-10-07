<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Http\Requests;

use App\Domains\Notifications\ValueObjects\NotificationScope;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Shared\ValueObjects\Pagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListStaffNotificationsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::enum(NotificationScope::class)],
            'status' => ['sometimes', Rule::enum(NotificationStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAXIMUM_PAGE],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAXIMUM_PER_PAGE],
        ];
    }
}
