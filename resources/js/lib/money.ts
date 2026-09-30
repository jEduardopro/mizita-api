export const CENTS_PER_UNIT = 100;

const ZERO_AMOUNT = /^0+(?:\.0+)?$/;

const DECIMAL_AMOUNT = /^\d*(?:\.\d{0,2})?$/;

const FRACTION_DIGITS = 2;

const MONEY_LOCALE = 'en-US';

const MONEY_CURRENCY_DISPLAY = 'narrowSymbol';

const MONEY_SIGN_DISPLAY = 'negative';

const FIXED_MONEY_CURRENCY_CODE = 'MXN';

const CURRENCY_CODE_SEPARATOR = ' ';

const moneyFormats = new Map<string, Intl.NumberFormat>();

function createMoneyFormat(currencyCode: string): Intl.NumberFormat {
    const numberOptions = {
        minimumFractionDigits: FRACTION_DIGITS,
        maximumFractionDigits: FRACTION_DIGITS,
        signDisplay: MONEY_SIGN_DISPLAY,
    } as const;

    try {
        return new Intl.NumberFormat(MONEY_LOCALE, {
            ...numberOptions,
            style: 'currency',
            currency: currencyCode,
            currencyDisplay: MONEY_CURRENCY_DISPLAY,
        });
    } catch {
        return new Intl.NumberFormat(MONEY_LOCALE, numberOptions);
    }
}

function moneyFormatFor(currencyCode: string): Intl.NumberFormat {
    const cached = moneyFormats.get(currencyCode);

    if (cached !== undefined) {
        return cached;
    }

    const format = createMoneyFormat(currencyCode);
    moneyFormats.set(currencyCode, format);

    return format;
}

function formatSymbolAmount(value: number, currencyCode: string): string {
    return moneyFormatFor(currencyCode).format(value);
}

function formatCodedAmount(value: number, currencyCode: string): string {
    return `${formatSymbolAmount(value, currencyCode)}${CURRENCY_CODE_SEPARATOR}${currencyCode}`;
}

export function isFreeAmount(amount: string): boolean {
    return ZERO_AMOUNT.test(amount.trim());
}

export function formatMoney(amount: string, currencyCode: string): string {
    const value = Number.parseFloat(amount);

    if (! Number.isFinite(value)) {
        return amount;
    }

    return formatCodedAmount(value, currencyCode);
}

export function formatMoneyFromCents(cents: number, currencyCode: string): string {
    return formatCodedAmount(cents / CENTS_PER_UNIT, currencyCode);
}

export function formatFixedMoneyFromCents(cents: number): string {
    return formatSymbolAmount(cents / CENTS_PER_UNIT, FIXED_MONEY_CURRENCY_CODE);
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
