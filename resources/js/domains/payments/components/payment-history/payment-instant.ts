import { formatInstantTimeOfDay } from '@/lib/time';
import { formatTransactionDate } from '../payment-dates';

const DATE_TIME_SEPARATOR = ' · ';

export function paymentInstantFormatter(timezone: string, locale: string): (instant: string) => string {
    return (instant) =>
        `${formatTransactionDate(instant, timezone, locale)}${DATE_TIME_SEPARATOR}${formatInstantTimeOfDay(instant, timezone)}`;
}
