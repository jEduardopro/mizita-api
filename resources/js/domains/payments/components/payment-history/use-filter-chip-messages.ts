import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { OptionsFilterChipMessages } from '@/components/admin/filter-chips/OptionsFilterChip';

export function useFilterChipMessages(): OptionsFilterChipMessages {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return useMemo(
        () => ({
            apply: t('payments.history.filters.apply'),
            clear: t('payments.history.filters.clear'),
            searchPlaceholder: t('payments.history.filters.search'),
            empty: t('payments.history.filters.noOptions'),
            error: t('payments.history.filters.optionsError'),
            retry: tCommon('actions.tryAgain'),
        }),
        [t, tCommon],
    );
}
