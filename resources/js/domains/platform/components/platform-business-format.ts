const CREATED_DATE_FORMAT: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};

export function createdDateFormatter(locale: string): (instant: string) => string {
    const formatter = new Intl.DateTimeFormat(locale, CREATED_DATE_FORMAT);

    return (instant) => {
        const moment = new Date(instant);

        return Number.isNaN(moment.getTime()) ? instant : formatter.format(moment);
    };
}
