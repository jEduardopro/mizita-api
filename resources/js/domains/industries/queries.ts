import { useQuery } from '@tanstack/react-query';
import type { TFunction } from 'i18next';
import { useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { ComboboxOption } from '@/components/form/use-combobox';
import { i18n } from '@/lib/i18n';
import industriesEn from '@/locales/en/industries.json';
import { listIndustries } from './api';
import type { Industry } from './types';

/**
 * Seeded reference data that changes about once a year. `gcTime` matches, or the
 * catalogue would be collected as soon as the only screen using it unmounts.
 */
const CATALOG_LIFETIME_MS = 24 * 60 * 60 * 1000;

/** Pinned last: `other` is the escape hatch, not an entry in the alphabet. */
const PINNED_LAST = 'other';

export const industryKeys = {
    all: ['industries'] as const,
    list: () => [...industryKeys.all, 'list'] as const,
};

export function useIndustries() {
    return useQuery({
        queryKey: industryKeys.list(),
        queryFn: ({ signal }) => listIndustries(signal),
        staleTime: CATALOG_LIFETIME_MS,
        gcTime: CATALOG_LIFETIME_MS,
    });
}

type IndustryKey = keyof typeof industriesEn;

/**
 * The server owns the list and the front end owns the words, so the two can
 * drift by one deploy. An untranslated key renders as itself — plainly wrong to
 * look at, and still pickable — rather than as a blank row.
 */
function isTranslated(key: string): key is IndustryKey {
    return key in industriesEn;
}

function toOption(industry: Industry, t: TFunction<'industries'>): ComboboxOption {
    return {
        value: industry.id,
        label: isTranslated(industry.key) ? t(industry.key) : industry.key,
    };
}

/**
 * The collator is what makes this correct rather than merely sorted: a code
 * point comparison puts `Éxito del cliente` after `Ventas` in Spanish.
 *
 * The pinned row is recognised by its label, because an option carries the
 * server's uuid and a uuid says nothing about which row is the escape hatch.
 */
export function sortIndustryOptions(options: ComboboxOption[], language: string): ComboboxOption[] {
    const pinnedLabel = i18n.t(PINNED_LAST, { ns: 'industries', lng: language });
    const collator = new Intl.Collator(language, { sensitivity: 'base' });

    return [...options].sort((left, right) => {
        const leftPinned = left.label === pinnedLabel;
        const rightPinned = right.label === pinnedLabel;

        if (leftPinned !== rightPinned) {
            return leftPinned ? 1 : -1;
        }

        return collator.compare(left.label, right.label);
    });
}

type IndustryChoices = {
    options: ComboboxOption[];
    isPending: boolean;
    isError: boolean;
    refetch: () => void;
};

/** Ordering depends on the language, which is not a form's concern. */
export function useIndustryOptions(): IndustryChoices {
    const { data, isPending, isError, refetch } = useIndustries();
    const { t, i18n: instance } = useTranslation('industries');
    const language = instance.language;

    const options = useMemo(
        () => (data ? sortIndustryOptions(data.map((industry) => toOption(industry, t)), language) : []),
        [data, t, language],
    );

    const retry = useCallback(() => void refetch(), [refetch]);

    return { options, isPending, isError, refetch: retry };
}
