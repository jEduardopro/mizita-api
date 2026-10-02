<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Requests;

use App\Domains\Platform\ValueObjects\PlatformAdminEmail;
use Illuminate\Foundation\Http\FormRequest;

final class PlatformLoginRequest extends FormRequest
{
    public const EMAIL = 'email';

    public const PASSWORD = 'password';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            self::EMAIL => ['required', 'string', 'email', 'max:'.PlatformAdminEmail::MAXIMUM_LENGTH],
            self::PASSWORD => ['required', 'string'],
        ];
    }

    public function email(): string
    {
        return (string) $this->validated(self::EMAIL);
    }

    public function password(): string
    {
        return (string) $this->validated(self::PASSWORD);
    }
}
