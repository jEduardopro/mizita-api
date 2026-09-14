<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Tests\Support\Architecture\CommentedConformingUseCase;
use Tests\Support\Architecture\ConformingUseCase;
use Tests\Support\Architecture\DomainLayers;
use Tests\Support\Architecture\LateValidatingUseCase;
use Tests\Support\Architecture\UnvalidatedInput;
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

function mizitaFirstStatementOf(ReflectionMethod $method): string
{
    $file = (string) $method->getFileName();
    $lines = array_slice(
        file($file, FILE_IGNORE_NEW_LINES) ?: [],
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    );

    $body = substr((string) strstr(implode("\n", $lines), '{'), 1);
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

        return $line;
    }

    return '';
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

it('opens every use case handling a validatable DTO with a call to validate', function () {
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
        $expected = '$'.$parameter->getName().'->validate();';

        if (mizitaFirstStatementOf($handle) !== $expected) {
            $offenders[] = $useCase.'::handle() should open with '.$expected;
        }
    }

    Assert::assertNotEmpty(
        $covered,
        'No use case takes a DTO with a validate() method, so this rule is not checking anything.',
    );

    Assert::assertSame(
        [],
        $offenders,
        "A use case taking a self-validating DTO must call validate() as the very first statement of handle(), so the console, the queue and the next transport get the same verdict as HTTP:\n  - "
        .implode("\n  - ", $offenders),
    );
});

describe('the detectors behind the two rules above, shown failing on code that breaks them', function () {
    it('reads the opening statement of a conforming handle', function () {
        expect(mizitaFirstStatementOf(new ReflectionMethod(ConformingUseCase::class, 'handle')))
            ->toBe('$input->validate();');
    });

    it('sees past a comment the backend has not stripped yet', function () {
        expect(mizitaFirstStatementOf(new ReflectionMethod(CommentedConformingUseCase::class, 'handle')))
            ->toBe('$input->validate();');
    });

    it('refuses a handle that validates after it has already done work', function () {
        expect(mizitaFirstStatementOf(new ReflectionMethod(LateValidatingUseCase::class, 'handle')))
            ->not->toBe('$input->validate();');
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
