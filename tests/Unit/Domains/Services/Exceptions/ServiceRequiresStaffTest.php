<?php

declare(strict_types=1);

use App\Domains\Services\Exceptions\ServiceRequiresStaff;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says a service cannot exist without somebody to perform it', function () {
    expect(ServiceRequiresStaff::none()->getMessage())
        ->toBe('A service must be performed by at least one staff member.');
});

it('answers with the error code the client is shown a sentence for', function () {
    expect(ServiceRequiresStaff::none()->errorCode())->toBe('service_requires_staff');
});

it('classifies the refusal as a 422, because the selection is what has to change', function () {
    expect(ServiceRequiresStaff::none()->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(ServiceRequiresStaff::none())->toBeInstanceOf(DomainFailure::class)
        ->and(ServiceRequiresStaff::none())->toBeInstanceOf(Throwable::class);
});

it('is its own refusal, so nobody reads it as a staff member that does not exist', function () {
    expect(ServiceRequiresStaff::none()->errorCode())->not->toBe('unknown_staff_member');
});

it('names no staff member, because the whole point is that there was none', function () {
    expect(ServiceRequiresStaff::none()->getMessage())->not->toContain('[');
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][ServiceRequiresStaff::none()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
