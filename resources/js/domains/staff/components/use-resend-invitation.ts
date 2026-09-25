import { useTranslation } from 'react-i18next';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useResendTeamInvitation } from '../queries';

export type InvitationResender = {
    resend: () => void;
    isPending: boolean;
};

export function useResendInvitation(memberId: string): InvitationResender {
    const { t } = useTranslation('admin');
    const resendInvitation = useResendTeamInvitation();

    async function resend() {
        try {
            await resendInvitation.mutateAsync(memberId);
            raiseSuccessToast(t('team.toasts.invitationResent'));
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('team.errors.resendFailed')));
        }
    }

    return {
        resend: () => void resend(),
        isPending: resendInvitation.isPending,
    };
}
