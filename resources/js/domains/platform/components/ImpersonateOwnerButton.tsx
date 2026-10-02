import { LogIn, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useImpersonateOwner } from './use-impersonate-owner';

type Props = {
    businessId: string;
    businessName: string;
    ownerName: string;
    label: string;
    className?: string;
};

export function ImpersonateOwnerButton({
    businessId,
    businessName,
    ownerName,
    label,
    className,
}: Props) {
    const { t } = useTranslation('platform');
    const { t: tCommon } = useTranslation('common');
    const [open, setOpen] = useState(false);
    const { enterAsOwner, isEntering } = useImpersonateOwner();

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    aria-label={t('businesses.impersonate.actionFor', { business: businessName })}
                    className={className}
                >
                    <LogIn aria-hidden="true" />
                    {label}
                </Button>
            </AlertDialogTrigger>

            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <LogIn aria-hidden="true" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>
                        {t('businesses.impersonate.title', { business: businessName })}
                    </AlertDialogTitle>

                    <AlertDialogDescription>
                        {t('businesses.impersonate.body', { business: businessName, owner: ownerName })}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <p className="flex items-start gap-2 rounded-lg bg-muted px-3 py-2.5 text-left text-sm text-pretty text-muted-foreground">
                    <ShieldAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                    {t('businesses.impersonate.restrictions')}
                </p>

                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isEntering} className="h-11 px-4 md:h-9">
                        {tCommon('actions.cancel')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        variant="brand"
                        disabled={isEntering}
                        aria-busy={isEntering}
                        onClick={(event) => {
                            event.preventDefault();
                            enterAsOwner(businessId);
                        }}
                        className="h-11 px-4 md:h-9"
                    >
                        {isEntering
                            ? t('businesses.impersonate.confirming')
                            : t('businesses.impersonate.confirm')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
