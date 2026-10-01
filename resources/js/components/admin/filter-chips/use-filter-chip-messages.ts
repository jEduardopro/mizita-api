import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { OptionsFilterChipMessages } from './OptionsFilterChip';

export function useFilterChipMessages(): OptionsFilterChipMessages {
    const { t } = useTranslation('common');

    return useMemo(
        () => ({
            apply: t('filters.apply'),
            clear: t('filters.clear'),
            searchPlaceholder: t('filters.search'),
            empty: t('filters.noOptions'),
            error: t('filters.optionsError'),
            retry: t('actions.tryAgain'),
        }),
        [t],
    );
}
