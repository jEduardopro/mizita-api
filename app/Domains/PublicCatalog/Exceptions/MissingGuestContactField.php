<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class MissingGuestContactField extends DomainException implements DomainFailure
{
    private const PHONE_CODE = 'missing_guest_phone';

    private const EMAIL_CODE = 'missing_guest_email';

    private const ADDRESS_CODE = 'missing_guest_address';

    private function __construct(string $message, private readonly string $failureCode)
    {
        parent::__construct($message);
    }

    public static function phone(): self
    {
        return new self('This business requires a phone number to book.', self::PHONE_CODE);
    }

    public static function email(): self
    {
        return new self('This business requires an email address to book.', self::EMAIL_CODE);
    }

    public static function address(): self
    {
        return new self('This business requires a street, city, state and postal code to book.', self::ADDRESS_CODE);
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
