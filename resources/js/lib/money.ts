export const CENTS_PER_UNIT = 100;

const ZERO_AMOUNT = /^0+(?:\.0+)?$/;

const DECIMAL_AMOUNT = /^\d*(?:\.\d{0,2})?$/;

const FRACTION_DIGITS = 2;

export function isFreeAmount(amount: string): boolean {
    return ZERO_AMOUNT.test(amount.trim());
}

export function formatAmount(amount: string, locale: string): string {
    const value = Number.parseFloat(amount);

    if (! Number.isFinite(value)) {
        return amount;
    }

    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(value);
}

export function formatMoney(amount: string, currencyCode: string, locale: string): string {
    const value = Number.parseFloat(amount);

    if (! Number.isFinite(value)) {
        return amount;
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: currencyCode,
            minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return formatAmount(amount, locale);
    }
}

export function centsFromDecimalString(value: string): number {
    const trimmed = value.trim();

    if (! DECIMAL_AMOUNT.test(trimmed)) {
        return 0;
    }

    const [whole, fraction = ''] = trimmed.split('.');

    return (
        Number(whole === '' ? 0 : whole) * CENTS_PER_UNIT +
        Number(fraction.padEnd(FRACTION_DIGITS, '0'))
    );
}

export function decimalStringFromCents(cents: number): string {
    const total = Math.max(0, Math.round(cents));
    const fraction = String(total % CENTS_PER_UNIT).padStart(FRACTION_DIGITS, '0');

    return `${Math.floor(total / CENTS_PER_UNIT)}.${fraction}`;
}

export function formatMoneyFromCents(cents: number, currencyCode: string, locale: string): string {
    return formatMoney(decimalStringFromCents(cents), currencyCode, locale);
}
