import { SearchX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

type Props = {
    search: string;
    onClearSearch: () => void;
};

export function IntegrationsEmptySearch({ search, onClearSearch }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <div
            role="status"
            className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center"
        >
            <SearchX aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{t('integrations.empty.title')}</p>

            <p className="max-w-sm text-sm text-pretty break-words text-muted-foreground">
                {t('integrations.empty.body', { search })}
            </p>

            <Button type="button" variant="outline" onClick={onClearSearch} className="h-11 px-4 md:h-9">
                {tCommon('actions.clear')}
            </Button>
        </div>
    );
}
