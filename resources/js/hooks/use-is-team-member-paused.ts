import { useCallback } from 'react';
import { usePlan } from '@/hooks/use-plan';
import type { RoleName } from '@/lib/authorization';

export type TeamMemberPauseCheck = (role: RoleName) => boolean;

export function useIsTeamMemberPaused(): TeamMemberPauseCheck {
    const { includes } = usePlan();
    const includesTeam = includes('team');

    return useCallback((role: RoleName) => ! includesTeam && role !== 'owner', [includesTeam]);
}
