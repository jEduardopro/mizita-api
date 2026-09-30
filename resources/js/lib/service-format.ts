import type { TFunction } from 'i18next';
import { formatMoney, isFreeAmount } from '@/lib/money';

type AdminTranslate = TFunction<'admin'>;

export function formatDuration(minutes: number, t: AdminTranslate): string {
    return t('services.duration', { count: minutes });
}

export function formatBuffer(minutes: number, t: AdminTranslate): string {
    if (minutes === 0) {
        return t('services.noBuffer');
    }

    return t('services.buffer', { count: minutes });
}

export function formatPrice(price: string, currencyCode: string, t: AdminTranslate): string {
    if (isFreeAmount(price)) {
        return t('services.free');
    }

    return formatMoney(price, currencyCode);
}

type ServiceSummarySource = {
    duration_minutes: number;
    buffer_minutes: number;
    price: string;
};

export function formatServiceSummary(
    service: ServiceSummarySource,
    currencyCode: string,
    t: AdminTranslate,
): string {
    const duration = formatDuration(service.duration_minutes, t);
    const price = formatPrice(service.price, currencyCode, t);

    if (service.buffer_minutes > 0) {
        return t('services.summaryWithBuffer', {
            duration,
            buffer: formatBuffer(service.buffer_minutes, t),
            price,
        });
    }

    return t('services.summary', { duration, price });
}
