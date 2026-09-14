<?php

declare(strict_types=1);

use App\Domains\Industries\Application\Dtos\IndustryData;
use App\Domains\Industries\Infrastructure\Http\Resources\IndustryResource;
use App\Http\Responses\ApiResponder;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Application\Warning;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

function registerWarningLine(string $locale, string $code, string $message): void
{
    $translator = app('translator');
    $translator->get('messages.warnings', [], $locale);
    $translator->addLines(['messages.warnings.'.$code => $message], $locale);
}

beforeEach(function () {
    app()->setLocale('en');

    registerWarningLine('en', 'phone_not_saved', 'We could not save the phone number.');
    registerWarningLine('en', 'welcome_email_not_sent', 'We could not send the welcome email.');

    $this->logger = Mockery::spy(LoggerInterface::class);
    $this->responder = new ApiResponder($this->logger);
});

/**
 * @return array<string, mixed>
 */
function bodyOf(JsonResponse $response): array
{
    return (array) $response->getData(true);
}

function industryData(string $id = 'industry-uuid', string $key = 'hair_salon', int $position = 3): IndustryData
{
    return new IndustryData($id, $key, $position);
}

describe('success', function () {
    it('wraps the resource in the data envelope and adds nothing else', function () {
        $response = $this->responder->success(
            UseCaseResponse::success(industryData()),
            IndustryResource::make(industryData()),
            Response::HTTP_OK,
        );

        expect(bodyOf($response))->toBe([
            'data' => ['id' => 'industry-uuid', 'key' => 'hair_salon', 'position' => 3],
        ]);
    });

    it('answers with the status the controller asked for', function (int $status) {
        $response = $this->responder->success(
            UseCaseResponse::success(industryData()),
            IndustryResource::make(industryData()),
            $status,
        );

        expect($response->getStatusCode())->toBe($status);
    })->with([
        'created' => Response::HTTP_CREATED,
        'ok' => Response::HTTP_OK,
    ]);

    it('emits no warnings key when the response carries none', function () {
        $response = $this->responder->success(
            UseCaseResponse::success(industryData()),
            IndustryResource::make(industryData()),
            Response::HTTP_CREATED,
        );

        expect(bodyOf($response))->not->toHaveKey('warnings')
            ->and($response->getContent())->not->toContain('warnings');
    });

    it('appends the warnings the response carries beside the data', function () {
        $response = $this->responder->success(
            UseCaseResponse::success(industryData())->addWarning('phone_not_saved'),
            IndustryResource::make(industryData()),
            Response::HTTP_CREATED,
        );

        expect(bodyOf($response))->toBe([
            'data' => ['id' => 'industry-uuid', 'key' => 'hair_salon', 'position' => 3],
            'warnings' => [
                ['code' => 'phone_not_saved', 'message' => 'We could not save the phone number.'],
            ],
        ]);
    });

    it('keeps the warnings in the order the use case reported them', function () {
        $response = $this->responder->success(
            UseCaseResponse::success(industryData())
                ->addWarning('welcome_email_not_sent')
                ->addWarning('phone_not_saved'),
            IndustryResource::make(industryData()),
            Response::HTTP_CREATED,
        );

        expect(array_column(bodyOf($response)['warnings'], 'code'))
            ->toBe(['welcome_email_not_sent', 'phone_not_saved']);
    });

    it('translates a warning in the locale the request resolved', function () {
        app()->setLocale('es');
        registerWarningLine('es', 'phone_not_saved', 'No hemos podido guardar el teléfono.');

        $response = $this->responder->success(
            UseCaseResponse::success(industryData())->addWarning('phone_not_saved'),
            IndustryResource::make(industryData()),
            Response::HTTP_CREATED,
        );

        expect(bodyOf($response)['warnings'][0]['message'])->toBe('No hemos podido guardar el teléfono.');
    });

    it('wraps a resource collection in the same data envelope', function () {
        $industries = [industryData('first-uuid', 'hair_salon', 1), industryData('second-uuid', 'barbershop', 2)];

        $response = $this->responder->success(
            UseCaseResponse::success($industries),
            IndustryResource::collection($industries),
            Response::HTTP_OK,
        );

        expect(bodyOf($response))->toBe([
            'data' => [
                ['id' => 'first-uuid', 'key' => 'hair_salon', 'position' => 1],
                ['id' => 'second-uuid', 'key' => 'barbershop', 'position' => 2],
            ],
        ]);
    });

    it('emits no warnings key for a resource collection without warnings', function () {
        $industries = [industryData('first-uuid', 'hair_salon', 1)];

        $response = $this->responder->success(
            UseCaseResponse::success($industries),
            IndustryResource::collection($industries),
            Response::HTTP_OK,
        );

        expect($response->getContent())->not->toContain('warnings');
    });

    it('appends the warnings to a resource collection too', function () {
        $industries = [industryData('first-uuid', 'hair_salon', 1)];

        $response = $this->responder->success(
            UseCaseResponse::success($industries)->addWarning('welcome_email_not_sent'),
            IndustryResource::collection($industries),
            Response::HTTP_OK,
        );

        expect(bodyOf($response)['warnings'])->toBe([
            ['code' => 'welcome_email_not_sent', 'message' => 'We could not send the welcome email.'],
        ]);
    });

    it('logs nothing, because a success is not an incident', function () {
        $this->responder->success(
            UseCaseResponse::success(industryData()),
            IndustryResource::make(industryData()),
            Response::HTTP_OK,
        );

        $this->logger->shouldNotHaveReceived('error');
    });
});

describe('failure', function () {
    it('answers with the status the failure kind maps to', function (DomainFailureKind $kind, int $status) {
        $response = $this->responder->failure(UseCaseError::of('no_business', $kind));

        expect($response->getStatusCode())->toBe($status);
    })->with([
        'invalid' => [DomainFailureKind::Invalid, 422],
        'conflict' => [DomainFailureKind::Conflict, 409],
        'not found' => [DomainFailureKind::NotFound, 404],
        'unauthenticated' => [DomainFailureKind::Unauthenticated, 401],
        'forbidden' => [DomainFailureKind::Forbidden, 403],
    ]);

    it('answers with the translated message and the code', function () {
        $response = $this->responder->failure(
            UseCaseError::of('no_business', DomainFailureKind::Forbidden),
        );

        expect(bodyOf($response))->toBe([
            'message' => 'This user does not belong to a business.',
            'code' => 'no_business',
        ]);
    });

    it('emits no warnings key when the failure carries none', function () {
        $response = $this->responder->failure(
            UseCaseError::of('no_business', DomainFailureKind::Forbidden),
        );

        expect(bodyOf($response))->not->toHaveKey('warnings')
            ->and($response->getContent())->not->toContain('warnings');
    });

    it('emits no warnings key when the warning list is explicitly empty', function () {
        $response = $this->responder->failure(
            UseCaseError::of('no_business', DomainFailureKind::Forbidden),
            [],
        );

        expect($response->getContent())->not->toContain('warnings');
    });

    it('appends the warnings when there are some', function () {
        $response = $this->responder->failure(
            UseCaseError::of('unsupported_phone_number', DomainFailureKind::Invalid),
            [new Warning('phone_not_saved')],
        );

        expect(bodyOf($response))->toBe([
            'message' => 'That is not a valid phone number for the selected country.',
            'code' => 'unsupported_phone_number',
            'warnings' => [
                ['code' => 'phone_not_saved', 'message' => 'We could not save the phone number.'],
            ],
        ]);
    });

    it('keeps the warnings in the order the use case reported them', function () {
        $response = $this->responder->failure(
            UseCaseError::of('no_business', DomainFailureKind::Forbidden),
            [new Warning('welcome_email_not_sent'), new Warning('phone_not_saved')],
        );

        expect(array_column(bodyOf($response)['warnings'], 'code'))
            ->toBe(['welcome_email_not_sent', 'phone_not_saved']);
    });

    it('translates a warning in the locale the request resolved', function () {
        app()->setLocale('es');
        registerWarningLine('es', 'phone_not_saved', 'No hemos podido guardar el teléfono.');

        $response = $this->responder->failure(
            UseCaseError::of('no_business', DomainFailureKind::Forbidden),
            [new Warning('phone_not_saved')],
        );

        expect(bodyOf($response)['warnings'][0]['message'])->toBe('No hemos podido guardar el teléfono.');
    });

    it('logs nothing, because a domain failure is not an incident', function () {
        $this->responder->failure(UseCaseError::of('no_business', DomainFailureKind::Forbidden));

        $this->logger->shouldNotHaveReceived('error');
    });
});

describe('unexpected', function () {
    it('answers 500 with the opaque server error body', function () {
        $response = $this->responder->unexpected(Request::create('/api/businesses', 'POST'), new RuntimeException('boom'));

        expect($response->getStatusCode())->toBe(500)
            ->and(bodyOf($response))->toBe([
                'message' => 'Something went wrong on our side. Please try again.',
                'code' => 'server_error',
            ]);
    });

    it('never puts the exception message on the wire', function () {
        $response = $this->responder->unexpected(
            Request::create('/api/businesses', 'POST'),
            new RuntimeException('SQLSTATE[42P01]: undefined_table businesses'),
        );

        expect($response->getContent())->not->toContain('SQLSTATE');
    });

    it('logs the failure with the exception message and the request context', function () {
        $error = new RuntimeException('boom');

        $this->responder->unexpected(Request::create('/api/businesses', 'POST'), $error);

        $this->logger->shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'boom'
                && $context['exception'] === $error
                && $context['method'] === 'POST'
                && $context['path'] === 'api/businesses');
    });
});
