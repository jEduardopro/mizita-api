<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlatformTwoFactorRequest extends FormRequest
{
    public const CODE = 'code';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            self::CODE => ['required', 'string', 'digits:6'],
        ];
    }

    public function code(): string
    {
        return (string) $this->validated(self::CODE);
    }
}
