import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    hasPermission,
    hasPermissions,
    hasRole,
    type PermissionName,
    type RoleName,
} from '@/lib/authorization';

export type Authorization = {
    roles: readonly RoleName[];
    permissions: readonly PermissionName[];
    can: (permission: PermissionName) => boolean;
    canAll: (permissions: readonly PermissionName[]) => boolean;
    canAny: (permissions: readonly PermissionName[]) => boolean;
    is: (role: RoleName) => boolean;
};

export function useAuthorization(): Authorization {
    const { auth } = usePage().props;

    return useMemo<Authorization>(
        () => ({
            roles: auth.roles,
            permissions: auth.permissions,
            can: (permission) => hasPermission(permission, auth.permissions),
            canAll: (permissions) => hasPermissions(permissions, auth.permissions, 'all'),
            canAny: (permissions) => hasPermissions(permissions, auth.permissions, 'any'),
            is: (role) => hasRole(role, auth.roles),
        }),
        [auth],
    );
}
