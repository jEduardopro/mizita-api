import type { TFunction } from 'i18next';
import { formatMoney, isFreeAmount } from '@/lib/money';
import { formatInstantTimeOfDay } from '@/lib/time';
import type { PublicService, PublicTeamMember } from '../../types';

export type BookingSummaryLine = {
    id: string;
    label: string;
    value: string;
};

export type BookingSummaryInput = {
    service: PublicService | null;
    staffMember: PublicTeamMember | null;
    startsAt: string | null;
    timezone: string;
    currencyCode: string;
    locale: string;
    t: TFunction<'public'>;
};

const MOMENT_DATE_FORMAT: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
};

function dayLabel(instant: string, timezone: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { ...MOMENT_DATE_FORMAT, timeZone: timezone }).format(
        new Date(instant),
    );
}

export function bookingMomentLabel(input: BookingSummaryInput, instant: string): string {
    return input.t('booking.flow.summary.moment', {
        day: dayLabel(instant, input.timezone, input.locale),
        time: formatInstantTimeOfDay(instant, input.timezone),
    });
}

export function bookingPriceLabel(price: string, currencyCode: string, locale: string, t: TFunction<'public'>): string {
    return isFreeAmount(price)
        ? t('booking.services.free')
        : formatMoney(price, currencyCode, locale);
}

export function bookingSummaryLines(input: BookingSummaryInput): BookingSummaryLine[] {
    const { service, staffMember, startsAt, currencyCode, locale, t } = input;
    const lines: BookingSummaryLine[] = [];

    if (service !== null) {
        lines.push({ id: 'service', label: t('booking.flow.summary.service'), value: service.name });
    }

    if (staffMember !== null) {
        lines.push({ id: 'staff', label: t('booking.flow.summary.staff'), value: staffMember.name });
    }

    if (startsAt !== null) {
        lines.push({
            id: 'when',
            label: t('booking.flow.summary.when'),
            value: bookingMomentLabel(input, startsAt),
        });
    }

    if (service !== null) {
        lines.push({
            id: 'duration',
            label: t('booking.flow.summary.duration'),
            value: t('booking.services.duration', { count: service.duration_minutes }),
        });

        lines.push({
            id: 'price',
            label: t('booking.flow.summary.price'),
            value: bookingPriceLabel(service.price, currencyCode, locale, t),
        });
    }

    return lines;
}
