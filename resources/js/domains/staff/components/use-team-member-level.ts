import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useUpdateTeamMember } from '../queries';
import type { AssignableStaffRole } from '../types';

export type TeamMemberLevelChanger = {
    level: AssignableStaffRole;
    change: (level: AssignableStaffRole) => void;
    isPending: boolean;
};

export function useTeamMemberLevel(memberId: string, savedLevel: AssignableStaffRole): TeamMemberLevelChanger {
    const { t } = useTranslation('admin');
    const updateMember = useUpdateTeamMember();
    const [pendingLevel, setPendingLevel] = useState<AssignableStaffRole | null>(null);

    async function save(level: AssignableStaffRole) {
        setPendingLevel(level);

        try {
            await updateMember.mutateAsync({ id: memberId, payload: { level } });
            raiseSuccessToast(t('team.toasts.levelChanged'));
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('team.errors.levelChangeFailed')));
        } finally {
            setPendingLevel(null);
        }
    }

    function change(level: AssignableStaffRole) {
        if (level === savedLevel || updateMember.isPending) {
            return;
        }

        void save(level);
    }

    return {
        level: pendingLevel ?? savedLevel,
        change,
        isPending: updateMember.isPending,
    };
}
