<?php

declare(strict_types=1);

use App\Shared\Application\UseCaseResponse;
use PHPUnit\Framework\Assert;
use Tests\Support\Architecture\BareValueUseCase;
use Tests\Support\Architecture\CommentedConformingUseCase;
use Tests\Support\Architecture\ConformingUseCase;
use Tests\Support\Architecture\DomainLayers;
use Tests\Support\Architecture\LateValidatingUseCase;
use Tests\Support\Architecture\UnvalidatedInput;
use Tests\Support\Architecture\UnvalidatedUseCase;
use Tests\Support\Architecture\ValidatableInput;

/**
 * @return list<class-string>
 */
function mizitaInputDtos(): array
{
    return array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => str_starts_with($class, 'App\Domains\\')
            && str_contains($class, '\Application\Dtos\\'),
    ));
}

/**
 * @return list<class-string>
 */
function mizitaUseCases(): array
{
    return array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => str_starts_with($class, 'App\Domains\\')
            && str_contains($class, '\Application\UseCases\\'),
    ));
}

function mizitaDeclares(string $class, string $method): bool
{
    $reflection = new ReflectionClass($class);

    return $reflection->hasMethod($method)
        && $reflection->getMethod($method)->getDeclaringClass()->getName() === $class;
}

/**
 * @return list<string>
 */
function mizitaLeadingStatementsOf(ReflectionMethod $method, int $limit): array
{
    $file = (string) $method->getFileName();
    $lines = array_slice(
        file($file, FILE_IGNORE_NEW_LINES) ?: [],
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    );

    $body = substr((string) strstr(implode("\n", $lines), '{'), 1);
    $statements = [];
    $inBlockComment = false;

    foreach (explode("\n", $body) as $line) {
        $line = trim($line);

        if ($inBlockComment) {
            $inBlockComment = ! str_contains($line, '*/');

            continue;
        }

        if ($line === '' || str_starts_with($line, '//')) {
            continue;
        }

        if (str_starts_with($line, '/*')) {
            $inBlockComment = ! str_contains($line, '*/');

            continue;
        }

        $statements[] = $line;

        if (count($statements) === $limit) {
            return $statements;
        }
    }

    return $statements;
}

function mizitaReturnTypeOf(ReflectionMethod $method): string
{
    $type = $method->getReturnType();

    return $type === null ? 'no return type at all' : (string) $type;
}

it('gives every input DTO built from an untrusted array a validate method', function () {
    $fromRequest = array_values(array_filter(
        mizitaInputDtos(),
        static fn (string $class): bool => mizitaDeclares($class, 'fromRequest'),
    ));

    $unvalidated = array_values(array_filter(
        $fromRequest,
        static fn (string $class): bool => ! mizitaDeclares($class, 'validate'),
    ));

    Assert::assertNotEmpty(
        $fromRequest,
        'No DTO declares fromRequest(), so this rule is not checking anything. Confirm DomainLayers still finds Application/Dtos.',
    );

    Assert::assertSame(
        [],
        $unvalidated,
        "These DTOs build themselves from an untrusted array but never rule on it:\n  - "
        .implode("\n  - ", $unvalidated)
        ."\nAdd a public validate(): void that throws a DomainFailure on the first problem. A DTO assembled from value objects should have no fromRequest() instead.",
    );
});

it('leaves a DTO that is built from value objects alone', function () {
    $sealed = ['AttachPhoneInput', 'RegisterBusinessOwnerInput', 'AuthenticateWithGoogleInput'];

    $offenders = array_values(array_filter(
        mizitaInputDtos(),
        static fn (string $class): bool => in_array(class_basename($class), $sealed, true)
            && mizitaDeclares($class, 'fromRequest'),
    ));

    expect($offenders)->toBe([]);
});

it('opens every use case handling a validatable DTO with a guarded call to validate', function () {
    $covered = [];
    $offenders = [];

    foreach (mizitaUseCases() as $useCase) {
        $handle = (new ReflectionClass($useCase))->getMethod('handle');
        $parameter = $handle->getParameters()[0] ?? null;
        $type = $parameter?->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            continue;
        }

        if (! mizitaDeclares($type->getName(), 'validate')) {
            continue;
        }

        $covered[] = $useCase;
        $expected = ['try {', '$'.$parameter->getName().'->validate();'];

        if (mizitaLeadingStatementsOf($handle, count($expected)) !== $expected) {
            $offenders[] = $useCase.'::handle() should open with '.implode(' ', $expected);
        }
    }

    Assert::assertNotEmpty(
        $covered,
        'No use case takes a DTO with a validate() method, so this rule is not checking anything.',
    );

    Assert::assertSame(
        [],
        $offenders,
        "A use case taking a self-validating DTO must call validate() as the very first statement inside the try that turns a DomainFailure into a failed UseCaseResponse, so the console, the queue and the next transport get the same verdict as HTTP:\n  - "
        .implode("\n  - ", $offenders),
    );
});

it('returns the one response every use case returns', function () {
    $useCases = mizitaUseCases();
    $offenders = [];

    foreach ($useCases as $useCase) {
        $handle = (new ReflectionClass($useCase))->getMethod('handle');
        $returnType = mizitaReturnTypeOf($handle);

        if ($returnType === UseCaseResponse::class) {
            continue;
        }

        $offenders[] = $useCase.'::handle() declares '.$returnType;
    }

    Assert::assertNotEmpty(
        $useCases,
        'No use case was found, so this rule is not checking anything. Confirm DomainLayers still finds Application/UseCases.',
    );

    Assert::assertSame(
        [],
        $offenders,
        "Every use case answers with a UseCaseResponse, so a caller decides between value() and error() instead of guessing which exceptions might escape:\n  - "
        .implode("\n  - ", $offenders),
    );
});

describe('the detectors behind the rules above, shown failing on code that breaks them', function () {
    it('reads the opening statements of a conforming handle', function () {
        expect(mizitaLeadingStatementsOf(new ReflectionMethod(ConformingUseCase::class, 'handle'), 2))
            ->toBe(['try {', '$input->validate();']);
    });

    it('sees past a comment the backend has not stripped yet', function () {
        expect(mizitaLeadingStatementsOf(new ReflectionMethod(CommentedConformingUseCase::class, 'handle'), 2))
            ->toBe(['try {', '$input->validate();']);
    });

    it('refuses a handle that validates after it has already done work', function () {
        expect(mizitaLeadingStatementsOf(new ReflectionMethod(LateValidatingUseCase::class, 'handle'), 2))
            ->not->toBe(['try {', '$input->validate();']);
    });

    it('refuses a handle that answers with a response but never validates', function () {
        expect(mizitaLeadingStatementsOf(new ReflectionMethod(UnvalidatedUseCase::class, 'handle'), 2))
            ->not->toBe(['try {', '$input->validate();']);
    });

    it('stops at the number of statements it was asked for', function () {
        expect(mizitaLeadingStatementsOf(new ReflectionMethod(ConformingUseCase::class, 'handle'), 1))
            ->toBe(['try {']);
    });

    it('reads the response type a conforming handle declares', function () {
        expect(mizitaReturnTypeOf(new ReflectionMethod(ConformingUseCase::class, 'handle')))
            ->toBe(UseCaseResponse::class);
    });

    it('spots a handle that validates first and still hands back a bare value', function () {
        expect(mizitaLeadingStatementsOf(new ReflectionMethod(BareValueUseCase::class, 'handle'), 2))
            ->toBe(['try {', '$input->validate();'])
            ->and(mizitaReturnTypeOf(new ReflectionMethod(BareValueUseCase::class, 'handle')))
            ->not->toBe(UseCaseResponse::class);
    });

    it('spots a DTO that builds itself from an array and rules on nothing', function () {
        expect(mizitaDeclares(UnvalidatedInput::class, 'fromRequest'))->toBeTrue()
            ->and(mizitaDeclares(UnvalidatedInput::class, 'validate'))->toBeFalse();
    });

    it('does not mistake an inherited method for a declared one', function () {
        expect(mizitaDeclares(ValidatableInput::class, 'validate'))->toBeTrue()
            ->and(mizitaDeclares(ValidatableInput::class, 'fromRequest'))->toBeFalse();
    });
});
