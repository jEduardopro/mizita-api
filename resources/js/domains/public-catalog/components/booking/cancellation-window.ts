import type { TFunction } from 'i18next';

const MINUTES_PER_HOUR = 60;

function windowDurationLabel(minutes: number, t: TFunction<'public'>): string {
    const wholeHours = Math.floor(minutes / MINUTES_PER_HOUR);
    const remainingMinutes = minutes % MINUTES_PER_HOUR;

    if (wholeHours === 0) {
        return t('booking.manage.windowMinutes', { count: remainingMinutes });
    }

    const hoursLabel = t('booking.manage.windowHours', { count: wholeHours });

    if (remainingMinutes === 0) {
        return hoursLabel;
    }

    return t('booking.manage.windowParts', {
        hours: hoursLabel,
        minutes: t('booking.manage.windowMinutes', { count: remainingMinutes }),
    });
}

export function cancellationWindowNote(minutes: number, t: TFunction<'public'>): string {
    return t('booking.manage.window', { window: windowDurationLabel(minutes, t) });
}
