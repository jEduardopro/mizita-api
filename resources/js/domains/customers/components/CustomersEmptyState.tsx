import { Link } from '@inertiajs/react';
import { SearchX, UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/hooks/use-authorization';
import { NEW_CUSTOMER_URL } from './customer-urls';

type Props = {
    search: string;
    onClearSearch: () => void;
};

export function CustomersEmptyState({ search, onClearSearch }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const { can } = useAuthorization();

    if (search !== '') {
        return (
            <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
                <SearchX aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="font-medium">{t('customers.empty.search.title')}</p>

                <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                    {t('customers.empty.search.body', { search })}
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
            <UserPlus aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{t('customers.empty.first.title')}</p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                {t('customers.empty.first.body')}
            </p>

            {can('create_customer') ? (
                <Button asChild variant="brand" className="h-11 px-4 md:h-9">
                    <Link href={NEW_CUSTOMER_URL}>{t('customers.actions.create')}</Link>
                </Button>
            ) : null}
        </div>
    );
}
