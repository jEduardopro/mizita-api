import type { TFunction } from 'i18next';
import { formatAmount, isFreeAmount } from '@/lib/money';

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

export function formatPrice(price: string, locale: string, t: AdminTranslate): string {
    if (isFreeAmount(price)) {
        return t('services.free');
    }

    return t('services.price', { amount: formatAmount(price, locale) });
}
