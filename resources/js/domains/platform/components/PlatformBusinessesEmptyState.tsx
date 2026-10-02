import { Building2, SearchX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

type Props = {
    search: string;
    onClearSearch: () => void;
};

export function PlatformBusinessesEmptyState({ search, onClearSearch }: Props) {
    const { t } = useTranslation('platform');
    const { t: tCommon } = useTranslation('common');

    if (search !== '') {
        return (
            <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
                <SearchX aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="font-medium">{t('businesses.empty.search.title')}</p>

                <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                    {t('businesses.empty.search.body', { search })}
                </p>

                <Button type="button" variant="outline" onClick={onClearSearch} className="h-11 px-4 md:h-9">
                    {tCommon('actions.clear')}
                </Button>
            </div>
        );
    }

    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
            <Building2 aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{t('businesses.empty.first.title')}</p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                {t('businesses.empty.first.body')}
            </p>
        </div>
    );
}
