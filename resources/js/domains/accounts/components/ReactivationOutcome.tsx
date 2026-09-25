import { Store } from 'lucide-react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import type { AccountReactivationStatus } from '../types';

type ClosedBusiness = NonNullable<AccountReactivationStatus['business']>;

type Props = {
    business: ClosedBusiness | null;
};

export function ReactivationOutcome({ business }: Props) {
    const { t } = useTranslation('auth');

    if (business === null) {
        return (
            <p className="border-t border-border bg-muted/40 p-4 text-pretty text-sm text-muted-foreground">
                {t('reactivateAccount.accountOnly')}
            </p>
        );
    }

    const messageKey = business.purged ? 'reactivateAccount.business.purged' : 'reactivateAccount.business.kept';

    return (
        <div
            className={cn(
                'flex items-start gap-3 border-t p-4',
                business.purged ? 'border-destructive/25 bg-destructive/5' : 'border-border bg-muted/40',
            )}
        >
            <Store
                aria-hidden="true"
                className={cn('mt-0.5 size-4 shrink-0', business.purged ? 'text-destructive' : 'text-primary')}
            />

            <div className="grid min-w-0 gap-1">
                <p className="truncate text-sm font-medium">{business.name}</p>
                <p className="text-pretty text-sm text-muted-foreground">
                    {t(messageKey, { business: business.name })}
                </p>
            </div>
        </div>
    );
}
