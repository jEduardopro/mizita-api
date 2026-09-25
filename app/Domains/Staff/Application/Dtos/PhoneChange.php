<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\InvalidProfilePhone;

final readonly class PhoneChange
{
    public function __construct(
        public ?ProfilePhoneInput $phone,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        if (! array_key_exists('phone', $payload)) {
            return null;
        }

        return new self(ProfilePhoneInput::fromPayload($payload['phone']));
    }

    /**
     * @throws InvalidProfilePhone
     */
    public function validate(): void
    {
        $this->phone?->validate();
    }
}
