import { useAuthorization } from '@/hooks/use-authorization';
import { usePlan } from '@/hooks/use-plan';

export type TeamInvitationAccess = 'allowed' | 'requires_upgrade' | 'denied';

export function useTeamInvitationAccess(): TeamInvitationAccess {
    const { can } = useAuthorization();
    const { includes } = usePlan();

    if (! can('create_staff_member')) {
        return 'denied';
    }

    return includes('team') ? 'allowed' : 'requires_upgrade';
}
