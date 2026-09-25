import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { InviteTeamMembersForm } from './InviteTeamMembersForm';
import { TeamDialogActions } from './TeamDialogActions';
import { TeamSurface } from './TeamSurface';
import { useInviteTeamForm } from './use-invite-team-form';

type Props = {
    onClose: () => void;
};

export function InviteTeamMembersDialog({ onClose }: Props) {
    const { t } = useTranslation('admin');
    const formId = useId();
    const form = useInviteTeamForm({ onInvited: onClose });

    return (
        <TeamSurface
            title={t('team.invite.title')}
            onClose={onClose}
            className="sm:max-w-3xl"
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
