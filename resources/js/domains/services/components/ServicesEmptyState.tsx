import { Link } from '@inertiajs/react';
import { PackageOpen, SearchX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/hooks/use-authorization';
import { NEW_SERVICE_URL } from './service-urls';

type Props = {
    search: string;
    onClearSearch: () => void;
};

export function ServicesEmptyState({ search, onClearSearch }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const { can } = useAuthorization();

    if (search !== '') {
        return (
            <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
                <SearchX aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="font-medium">{t('services.empty.search.title')}</p>

                <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                    {t('services.empty.search.body', { search })}
                </p>

                <Button
                    type="button"
                    variant="outline"
                    onClick={onClearSearch}
                    className="h-11 px-4 md:h-9"
                >
                    {tCommon('actions.clear')}
                </Button>
            </div>
        );
    }

    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
            <PackageOpen aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{t('services.empty.first.title')}</p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                {t('services.empty.first.body')}
            </p>

            {can('create_service') ? (
                <Button asChild variant="brand" className="h-11 px-4 md:h-9">
                    <Link href={NEW_SERVICE_URL}>{t('services.actions.create')}</Link>
                </Button>
            ) : null}
        </div>
    );
}
