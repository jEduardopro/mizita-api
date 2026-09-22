import { centsFromDecimalString, decimalStringFromCents } from '@/lib/money';

const MAXIMUM_DECIMALS = 2;

const MAXIMUM_PERCENT_BASIS_POINTS = 10_000;

const FULL_PERCENT = '100';

export function normalizeAmountInput(raw: string): string {
    const cleaned = raw.replace(',', '.').replace(/[^\d.]/g, '');
    const [whole, ...decimals] = cleaned.split('.');

    if (decimals.length === 0) {
        return whole;
    }

    return `${whole}.${decimals.join('').slice(0, MAXIMUM_DECIMALS)}`;
}

export function padAmountInput(raw: string): string {
    const [whole, decimals = ''] = raw.split('.');

    if (whole === '') {
        return '';
    }

    return `${whole}.${decimals.padEnd(MAXIMUM_DECIMALS, '0')}`;
}

export function centsFromAmountInput(raw: string): number {
    return centsFromDecimalString(raw);
}

export function amountInputFromCents(cents: number): string {
    return decimalStringFromCents(cents);
}

export function normalizePercentInput(raw: string): string {
    const normalized = normalizeAmountInput(raw);

    return centsFromDecimalString(normalized) > MAXIMUM_PERCENT_BASIS_POINTS
        ? FULL_PERCENT
        : normalized;
}
