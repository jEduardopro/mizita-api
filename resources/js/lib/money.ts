const ZERO_AMOUNT = /^0+(?:\.0+)?$/;

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
