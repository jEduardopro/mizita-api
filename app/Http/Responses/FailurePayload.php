<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Shared\ValueObjects\DomainFailureKind;
use Symfony\Component\HttpFoundation\Response;

final readonly class FailurePayload
{
    private const SERVER_ERROR_CODE = 'server_error';

    private const TRANSLATION_PREFIX = 'messages.errors.';

    /** @var array{message: string, code: string} */
    public array $body;

    private function __construct(
        public int $status,
        public string $code,
        public string $message,
    ) {
        $this->body = [
            'message' => $this->message,
            'code' => $this->code,
        ];
    }

    public static function for(string $code, DomainFailureKind $kind): self
    {
        return new self(
            status: self::statusFor($kind),
            code: $code,
            message: self::messageFor($code),
        );
    }

    public static function messageFor(string $code): string
    {
        return (string) __(self::TRANSLATION_PREFIX.$code);
    }

    public static function serverError(): self
    {
        return new self(
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
            code: self::SERVER_ERROR_CODE,
            message: self::messageFor(self::SERVER_ERROR_CODE),
        );
    }

    private static function statusFor(DomainFailureKind $kind): int
    {
        return match ($kind) {
            DomainFailureKind::Invalid => Response::HTTP_UNPROCESSABLE_ENTITY,
            DomainFailureKind::Conflict => Response::HTTP_CONFLICT,
            DomainFailureKind::NotFound => Response::HTTP_NOT_FOUND,
            DomainFailureKind::Unauthenticated => Response::HTTP_UNAUTHORIZED,
            DomainFailureKind::Forbidden => Response::HTTP_FORBIDDEN,
        };
    }
}
