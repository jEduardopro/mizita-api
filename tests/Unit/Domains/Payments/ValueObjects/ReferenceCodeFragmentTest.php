<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\ValueObjects\ReferenceCodeFragment;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('reading a fragment', function () {
    it('reads a fragment in the uppercase the reference codes are stored in', function (string $raw, string $fragment) {
        expect(ReferenceCodeFragment::of($raw)?->value)->toBe($fragment);
    })->with([
        'lowercase' => ['mz7k', 'MZ7K'],
        'mixed case' => ['Mz7k2Qp9', 'MZ7K2QP9'],
        'already uppercase' => ['MZ7K', 'MZ7K'],
        'digits only' => ['2749', '2749'],
        'a single zero' => ['0', '0'],
        'accented letters' => ['ñandú', 'ÑANDÚ'],
    ]);

    it('trims the whitespace around the fragment', function (string $raw) {
        expect(ReferenceCodeFragment::of($raw)?->value)->toBe('MZ7K');
    })->with(['spaces' => '  mz7k  ', 'tab' => "\tmz7k", 'newline' => "mz7k\n"]);

    it('filters on no fragment when there is nothing to look for', function (?string $raw) {
        expect(ReferenceCodeFragment::of($raw))->toBeNull();
    })->with(['null' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t", 'newline' => "\n"]);
});

describe('how long a fragment may run', function () {
    it('accepts a fragment as long as a reference code', function () {
        expect(ReferenceCodeFragment::of(str_repeat('a', ReferenceCodeFragment::MAXIMUM_LENGTH))?->value)
            ->toBe(str_repeat('A', ReferenceCodeFragment::MAXIMUM_LENGTH));
    });

    it('refuses a fragment one character longer than a reference code', function () {
        expect(fn () => ReferenceCodeFragment::of(str_repeat('a', ReferenceCodeFragment::MAXIMUM_LENGTH + 1)))
            ->toThrow(InvalidPaymentReportFilter::class, 'A booking reference filter may not run past 8 characters.');
    });

    it('measures the fragment after trimming it', function () {
        expect(ReferenceCodeFragment::of('   mz7k2qp9   ')?->value)->toBe('MZ7K2QP9');
    });

    it('counts characters rather than bytes', function () {
        expect(ReferenceCodeFragment::of(str_repeat('ñ', ReferenceCodeFragment::MAXIMUM_LENGTH))?->value)
            ->toBe(str_repeat('Ñ', ReferenceCodeFragment::MAXIMUM_LENGTH));
    });

    it('refuses nine accented characters', function () {
        expect(fn () => ReferenceCodeFragment::of(str_repeat('ñ', ReferenceCodeFragment::MAXIMUM_LENGTH + 1)))
            ->toThrow(InvalidPaymentReportFilter::class);
    });

    it('refuses with a failure the responder can classify', function () {
        $refusal = null;

        try {
            ReferenceCodeFragment::of('MZ7K2QP9X');
        } catch (InvalidPaymentReportFilter $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_payment_report_filter')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });
});
