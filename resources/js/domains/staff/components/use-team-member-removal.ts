import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast } from '@/lib/toast';
import { useCheckTeamMemberRemoval } from '../queries';
import type { TeamMemberRemoval } from '../types';

export type TeamMemberRemovalDialog = 'confirm' | 'blocked';

export type TeamMemberRemovalFlow = {
    dialog: TeamMemberRemovalDialog | null;
    isChecking: boolean;
    start: () => void;
    showBlocked: () => void;
    handleOpenChange: (open: boolean) => void;
};

export function useTeamMemberRemoval(memberId: string): TeamMemberRemovalFlow {
    const { t } = useTranslation('admin');
    const checkRemoval = useCheckTeamMemberRemoval();
    const [dialog, setDialog] = useState<TeamMemberRemovalDialog | null>(null);

    function openDialogFor(removal: TeamMemberRemoval) {
        if (removal.removable) {
            setDialog('confirm');

            return;
        }

        if (removal.blocker === 'upcoming_appointments') {
            setDialog('blocked');

            return;
        }

        raiseErrorToast(t('team.errors.ownerCannotBeRemoved'));
    }

    async function start() {
        if (checkRemoval.isPending) {
            return;
        }

        try {
            openDialogFor(await checkRemoval.mutateAsync(memberId));
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('team.errors.removalCheckFailed')));
        }
    }

    return {
        dialog,
        isChecking: checkRemoval.isPending,
        start: () => void start(),
        showBlocked: () => setDialog('blocked'),
        handleOpenChange: (open) => {
            if (! open) {
                setDialog(null);
            }
        },
    };
}
