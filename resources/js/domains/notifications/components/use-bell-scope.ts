import { useAuthorization } from '@/hooks/use-authorization';
import type { NotificationScope } from '../types';

const OWNER_SCOPE: NotificationScope = 'team';

const STAFF_SCOPE: NotificationScope = 'mine';

export function useBellScope(): NotificationScope {
    const { is } = useAuthorization();

    return is('owner') ? OWNER_SCOPE : STAFF_SCOPE;
}
