import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

const INTEGRATION_DETAIL_TABS = ['about', 'instructions'] as const;

type IntegrationDetailTab = (typeof INTEGRATION_DETAIL_TABS)[number];

const DEFAULT_TAB: IntegrationDetailTab = 'about';

const TAB_LABEL_KEYS = {
    about: 'integrations.detail.tabs.about',
    instructions: 'integrations.detail.tabs.instructions',
} as const satisfies Record<IntegrationDetailTab, string>;

type Props = {
    about: ReactNode;
    instructions: ReactNode;
};

export function IntegrationDetailTabs({ about, instructions }: Props) {
    const { t } = useTranslation('admin');

    const panels: Record<IntegrationDetailTab, ReactNode> = { about, instructions };

    return (
        <Tabs defaultValue={DEFAULT_TAB} className="gap-5">
            <TabsList
                variant="line"
                className="grid w-full grid-cols-2 border-b border-border pb-[5px] group-data-horizontal/tabs:h-auto sm:w-72"
            >
                {INTEGRATION_DETAIL_TABS.map((tab) => (
                    <TabsTrigger key={tab} value={tab} className="h-auto min-h-11 px-1">
                        {t(TAB_LABEL_KEYS[tab])}
                    </TabsTrigger>
                ))}
            </TabsList>

            {INTEGRATION_DETAIL_TABS.map((tab) => (
                <TabsContent key={tab} value={tab}>
                    {panels[tab]}
                </TabsContent>
            ))}
        </Tabs>
    );
}
