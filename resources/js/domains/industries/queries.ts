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
 * Industries are reference data: the list is seeded and changes about once a
 * year, so a remount or a language switch has nothing to re-ask the server for.
 * `gcTime` matches, otherwise the catalogue would be collected the moment the
 * only screen using it unmounts and fetched again on the way back.
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

/** The keys this bundle can name. English is the reference catalogue. */
type IndustryKey = keyof typeof industriesEn;

/**
 * Whether the catalogue has words for this key.
 *
 * The server owns the list and the front end owns the words, so the two can
 * drift by one deploy. A key with no translation is rendered as itself — plainly
 * wrong to look at, and still pickable — rather than as a blank row.
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
 * The choices in the order they are offered: alphabetical for the language on
 * screen, with `other` held back to the end.
 *
 * The collator is what makes this correct rather than merely sorted. A plain
 * comparison orders by code point, which in Spanish puts `Éxito del cliente`
 * after `Ventas` — every accented entry falls off the end of the list it
 * belongs in.
 *
 * The pinned row is recognised by its label, because an option carries the
 * server's uuid as its value and a uuid says nothing about which row is the
 * escape hatch. The label for `other` in this language is exactly what the row
 * was built from, so the two are the same string or the catalogue is missing a
 * key — in which case nothing is pinned and the list is merely alphabetical.
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
    /** Translated, sorted, and ready to hand to a combobox. */
    options: ComboboxOption[];
    isPending: boolean;
    isError: boolean;
    /** Asks for the catalogue again after a failure. */
    refetch: () => void;
};

/**
 * The catalogue as a field can consume it.
 *
 * Translating and ordering happen here rather than in the component because they
 * are one job — turning rows into choices — and because the order depends on the
 * language, which is not something a form should have to think about.
 */
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
