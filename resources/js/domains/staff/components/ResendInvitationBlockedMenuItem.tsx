import { MailPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';

const BLOCKED_ITEM = 'min-h-11 items-start py-1.5 text-muted-foreground data-disabled:opacity-100 md:min-h-8';

export function ResendInvitationBlockedMenuItem() {
    const { t } = useTranslation('admin');

    return (
        <DropdownMenuItem disabled className={BLOCKED_ITEM}>
            <MailPlus aria-hidden="true" className="mt-0.5" />

            <span className="grid gap-0.5">
                <span>{t('team.actions.resend')}</span>
                <span className="text-xs text-pretty">{t('plan.team.resendBlocked')}</span>
            </span>
        </DropdownMenuItem>
    );
}
