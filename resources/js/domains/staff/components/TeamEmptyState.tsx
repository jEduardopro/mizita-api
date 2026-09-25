import { SearchX, UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/hooks/use-authorization';

const EMPTY_STATE_FRAME =
    'grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center';

type Props = {
    search: string;
    onClearSearch: () => void;
    onInvite: () => void;
};

export function TeamEmptyState({ search, onClearSearch, onInvite }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const { can } = useAuthorization();

    if (search !== '') {
        return (
            <div className={EMPTY_STATE_FRAME}>
                <SearchX aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="font-medium">{t('team.empty.search.title')}</p>

                <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                    {t('team.empty.search.body', { search })}
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
        <div className={EMPTY_STATE_FRAME}>
            <UserPlus aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{t('team.empty.first.title')}</p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                {t('team.empty.first.body')}
            </p>

            {can('create_staff_member') ? (
                <Button type="button" variant="brand" onClick={onInvite} className="h-11 px-4 md:h-9">
                    {t('team.actions.invite')}
                </Button>
            ) : null}
        </div>
    );
}
