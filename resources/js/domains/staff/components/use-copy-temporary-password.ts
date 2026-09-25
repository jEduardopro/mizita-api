import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { copyPendingText } from '@/lib/clipboard';
import { errorCodeFrom, formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useRevealTemporaryPassword } from '../queries';
import { TEMPORARY_PASSWORD_UNAVAILABLE_CODE } from '../types';

export type TemporaryPasswordCopier = {
    copy: () => void;
    isCopying: boolean;
    hasCopied: boolean;
    isUnavailable: boolean;
};

export function useCopyTemporaryPassword(memberId: string): TemporaryPasswordCopier {
    const { t } = useTranslation('admin');
    const revealTemporaryPassword = useRevealTemporaryPassword();
    const [hasCopied, setHasCopied] = useState(false);

    async function copy() {
        const password = revealTemporaryPassword.mutateAsync(memberId);
        const [reveal, write] = await Promise.allSettled([password, copyPendingText(password)]);

        if (reveal.status === 'rejected') {
            raiseErrorToast(formMessageFrom(reveal.reason, t('team.temporaryPassword.revealFailed')));

            return;
        }

        revealTemporaryPassword.reset();

        if (write.status === 'rejected') {
            raiseErrorToast(t('team.temporaryPassword.copyFailed'));

            return;
        }

        setHasCopied(true);
        raiseSuccessToast(t('team.temporaryPassword.copied'));
    }

    return {
        copy: () => void copy(),
        isCopying: revealTemporaryPassword.isPending,
        hasCopied,
        isUnavailable: errorCodeFrom(revealTemporaryPassword.error) === TEMPORARY_PASSWORD_UNAVAILABLE_CODE,
    };
}
