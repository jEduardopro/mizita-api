import { useCallback } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import {
    NOTIFICATION_SCOPES,
    NOTIFICATION_STATUSES,
    type NotificationScope,
    type NotificationStatus,
} from '../types';

const STATUS_PARAMETER = 'status';

const SCOPE_PARAMETER = 'scope';

const DEFAULT_STATUS: NotificationStatus = 'unread';

const DEFAULT_SCOPE: NotificationScope = 'mine';

type NotificationFilters = {
    status: NotificationStatus;
    scope: NotificationScope;
    setStatus: (status: NotificationStatus) => void;
    setScope: (scope: NotificationScope) => void;
};

export function notificationStatusFrom(value: string | null): NotificationStatus {
    return NOTIFICATION_STATUSES.find((candidate) => candidate === value) ?? DEFAULT_STATUS;
}

export function notificationScopeFrom(value: string | null): NotificationScope {
    return NOTIFICATION_SCOPES.find((candidate) => candidate === value) ?? DEFAULT_SCOPE;
}

export function useNotificationFilters(): NotificationFilters {
    const { read, write } = useUrlQueryState();
    const status = notificationStatusFrom(read(STATUS_PARAMETER));
    const scope = notificationScopeFrom(read(SCOPE_PARAMETER));

    const setStatus = useCallback(
        (next: NotificationStatus) => write({ [STATUS_PARAMETER]: next === DEFAULT_STATUS ? null : next }),
        [write],
    );

    const setScope = useCallback(
        (next: NotificationScope) => write({ [SCOPE_PARAMETER]: next === DEFAULT_SCOPE ? null : next }),
        [write],
    );

    return { status, scope, setStatus, setScope };
}
