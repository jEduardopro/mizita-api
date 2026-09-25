import { Check, KeyRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useCopyTemporaryPassword } from './use-copy-temporary-password';

type Props = {
    memberId: string;
    describedBy: string;
};

export function TemporaryPasswordCopyButton({ memberId, describedBy }: Props) {
    const { t } = useTranslation('admin');
    const temporaryPassword = useCopyTemporaryPassword(memberId);

    const Icon = temporaryPassword.hasCopied ? Check : KeyRound;

    return (
        <Button
            type="button"
            variant={temporaryPassword.hasCopied ? 'outline' : 'brand-outline'}
            onClick={temporaryPassword.copy}
            disabled={temporaryPassword.isCopying || temporaryPassword.isUnavailable}
            aria-describedby={describedBy}
            className="h-11 w-full px-4 sm:w-auto md:h-9"
        >
            <Icon aria-hidden="true" />
            {temporaryPassword.hasCopied ? t('team.invited.copyAgain') : t('team.invited.copy')}
        </Button>
    );
}
