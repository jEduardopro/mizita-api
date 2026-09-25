import { useState } from 'react';
import type { Integration, IntegrationKey } from '../types';
import { INTEGRATION_PRESENTATIONS } from './integration-catalog';
import { IntegrationCard } from './IntegrationCard';
import { IntegrationCategorySection } from './IntegrationCategorySection';
import { IntegrationDetail } from './IntegrationDetail';
import { IntegrationsEmptySearch } from './IntegrationsEmptySearch';
import { IntegrationsSearch } from './IntegrationsSearch';
import { useIntegrationSections, type IntegrationSection } from './use-integration-sections';

type ResultsProps = {
    sections: IntegrationSection[];
    search: string;
    onClearSearch: () => void;
    onOpen: (key: IntegrationKey) => void;
};

function IntegrationResults({ sections, search, onClearSearch, onOpen }: ResultsProps) {
    if (sections.length === 0 && search.trim() !== '') {
        return <IntegrationsEmptySearch search={search.trim()} onClearSearch={onClearSearch} />;
    }

    return sections.map((section) => (
        <IntegrationCategorySection key={section.category} category={section.category}>
            {section.entries.map(({ integration, name, description }) => {
                const { Logo } = INTEGRATION_PRESENTATIONS[integration.key];

                return (
                    <li key={integration.key}>
                        <IntegrationCard
                            name={name}
                            description={description}
                            logo={<Logo />}
                            status={integration.connection?.status ?? null}
                            onOpen={() => onOpen(integration.key)}
                        />
                    </li>
                );
            })}
        </IntegrationCategorySection>
    ));
}

type Props = {
    integrations: Integration[];
    businessName: string | null;
};

export function IntegrationsDirectory({ integrations, businessName }: Props) {
    const [search, setSearch] = useState('');
    const [selectedKey, setSelectedKey] = useState<IntegrationKey | null>(null);
    const [isDetailOpen, setDetailOpen] = useState(false);
    const sections = useIntegrationSections(integrations, search);
    const selected = integrations.find((integration) => integration.key === selectedKey) ?? null;

    function openDetail(key: IntegrationKey) {
        setSelectedKey(key);
        setDetailOpen(true);
    }

    return (
        <div className="grid gap-8">
            <IntegrationsSearch value={search} onValueChange={setSearch} />

            <IntegrationResults
                sections={sections}
                search={search}
                onClearSearch={() => setSearch('')}
                onOpen={openDetail}
            />

            {selected === null ? null : (
                <IntegrationDetail
                    integration={selected}
                    businessName={businessName}
                    open={isDetailOpen}
                    onOpenChange={setDetailOpen}
                />
            )}
        </div>
    );
}
