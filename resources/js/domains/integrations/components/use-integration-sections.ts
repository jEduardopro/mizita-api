import { useTranslation } from 'react-i18next';
import { foldForSearch } from '@/lib/text';
import type { Integration, IntegrationCategory } from '../types';
import { INTEGRATION_PRESENTATIONS } from './integration-catalog';

export type IntegrationEntry = {
    integration: Integration;
    name: string;
    description: string;
};

export type IntegrationSection = {
    category: IntegrationCategory;
    entries: IntegrationEntry[];
};

function matchesSearch(entry: IntegrationEntry, foldedSearch: string): boolean {
    if (foldedSearch === '') {
        return true;
    }

    return foldForSearch(`${entry.name} ${entry.description}`).includes(foldedSearch);
}

export function useIntegrationSections(
    integrations: readonly Integration[],
    search: string,
): IntegrationSection[] {
    const { t } = useTranslation('admin');
    const foldedSearch = foldForSearch(search.trim());
    const entriesByCategory = new Map<IntegrationCategory, IntegrationEntry[]>();

    for (const integration of integrations) {
        const presentation = INTEGRATION_PRESENTATIONS[integration.key];
        const entry: IntegrationEntry = {
            integration,
            name: t(presentation.nameKey),
            description: t(presentation.descriptionKey),
        };

        if (! matchesSearch(entry, foldedSearch)) {
            continue;
        }

        entriesByCategory.set(integration.category, [
            ...(entriesByCategory.get(integration.category) ?? []),
            entry,
        ]);
    }

    return Array.from(entriesByCategory, ([category, entries]) => ({ category, entries }));
}
