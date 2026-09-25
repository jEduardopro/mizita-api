import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/hooks/use-authorization';
import { raiseSuccessToast } from '@/lib/toast';
import type { TeamMember } from '../types';
import { InvitedMembersSummary } from './InvitedMembersSummary';
import { InviteTeamMembersForm } from './InviteTeamMembersForm';
import { TeamDialogActions } from './TeamDialogActions';
import { TeamSurface } from './TeamSurface';
import { useInviteTeamForm } from './use-invite-team-form';

const SURFACE_WIDTH = 'sm:max-w-3xl';

type Props = {
    onClose: () => void;
};

export function InviteTeamMembersDialog({ onClose }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const formId = useId();
    const [invitedMembers, setInvitedMembers] = useState<TeamMember[] | null>(null);
    const form = useInviteTeamForm({ onInvited: concludeInvitation });

    function concludeInvitation(members: TeamMember[]) {
        const hasPasswordToHandOver = members.some((member) => member.temporary_password_available);

        if (can('reveal_temporary_password') && hasPasswordToHandOver) {
            setInvitedMembers(members);

            return;
        }

        raiseSuccessToast(t('team.toasts.invited', { count: members.length }));
        onClose();
    }

    if (invitedMembers !== null) {
        return (
            <TeamSurface
                title={t('team.invited.title', { count: invitedMembers.length })}
                onClose={onClose}
                className={SURFACE_WIDTH}
                footer={
                    <Button type="button" variant="brand" onClick={onClose} className="h-11 px-4 md:h-9">
                        {t('team.invited.done')}
                    </Button>
                }
            >
                <InvitedMembersSummary members={invitedMembers} />
            </TeamSurface>
        );
    }

    return (
        <TeamSurface
            title={t('team.invite.title')}
            onClose={onClose}
            className={SURFACE_WIDTH}
            footer={
                <TeamDialogActions
                    formId={formId}
                    label={t('team.invite.submit')}
                    submittingLabel={t('team.invite.submitting')}
                    isSubmitting={form.isSubmitting}
                    canSubmit={form.canSubmit}
                    onCancel={onClose}
                />
            }
        >
            <InviteTeamMembersForm id={formId} form={form} />
        </TeamSurface>
    );
}
