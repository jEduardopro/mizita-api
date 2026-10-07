<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Http\Requests;

use App\Domains\Notifications\ValueObjects\NotificationScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CountUnreadStaffNotificationsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::enum(NotificationScope::class)],
        ];
    }
}
