import { dateFromIso } from '@/components/form/date-format';
import type { DateRangeValue } from '@/components/form/date-range';

type DateFormatter = (date: Date) => string;

const RANGE_SEPARATOR = ' – ';

const ENGLISH_DATE_LOCALE = 'en-GB';

const SHORT_DATE: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};

const ENGLISH_ORDINAL_SUFFIXES: Record<Intl.LDMLPluralRule, string> = {
    zero: 'th',
    one: 'st',
    two: 'nd',
    few: 'rd',
    many: 'th',
    other: 'th',
};

function englishOrdinalDate(): DateFormatter {
    const formatter = new Intl.DateTimeFormat(ENGLISH_DATE_LOCALE, SHORT_DATE);
    const ordinals = new Intl.PluralRules(ENGLISH_DATE_LOCALE, { type: 'ordinal' });

    return (date) =>
        formatter
            .formatToParts(date)
            .map((part) =>
                part.type === 'day' ? `${part.value}${ENGLISH_ORDINAL_SUFFIXES[ordinals.select(Number(part.value))]}` : part.value,
            )
            .join('');
}

function localeShortDate(language: string): DateFormatter {
    const formatter = new Intl.DateTimeFormat(language, SHORT_DATE);

    return (date) => formatter.format(date);
}

const DATE_FORMATTERS_BY_LANGUAGE: Record<string, () => DateFormatter> = {
    en: englishOrdinalDate,
};

function dateFormatterFor(language: string): DateFormatter {
    const formatterOfLanguage = DATE_FORMATTERS_BY_LANGUAGE[language.split('-')[0]];

    return formatterOfLanguage === undefined ? localeShortDate(language) : formatterOfLanguage();
}

export function dateRangeLabelFormatter(language: string): (range: DateRangeValue) => string {
    const formatDate = dateFormatterFor(language);

    const formatIso = (value: string) => {
        const date = dateFromIso(value);

        return date === null ? '' : formatDate(date);
    };

    return (range) => {
        if (range.from === range.to) {
            return formatIso(range.from);
        }

        return `${formatIso(range.from)}${RANGE_SEPARATOR}${formatIso(range.to)}`;
    };
}
