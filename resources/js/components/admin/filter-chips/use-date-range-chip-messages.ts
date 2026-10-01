import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { DateRangeFilterChipMessages } from './DateRangeFilterChip';

export function useDateRangeChipMessages(): DateRangeFilterChipMessages {
    const { t } = useTranslation('common');

    return useMemo(
        () => ({
            cancel: t('actions.cancel'),
            apply: t('dateRange.apply'),
            clear: t('dateRange.clear'),
            presetsLabel: t('dateRange.presetsLabel'),
            presets: t('dateRange.presets', { returnObjects: true }),
            previousMonth: t('dateRange.previousMonth'),
            nextMonth: t('dateRange.nextMonth'),
            today: t('dateRange.today'),
        }),
        [t],
    );
}
