export type PermissionName =
    | 'manage_business'
    | 'manage_staff'
    | 'view_services'
    | 'create_service'
    | 'edit_service'
    | 'delete_service'
    | 'view_business_settings'
    | 'edit_business_settings';

export type RoleName = 'owner' | 'staff';

export type PermissionCheckMode = 'all' | 'any';

export function hasRole(role: RoleName, roles: readonly RoleName[]): boolean {
    return roles.includes(role);
}

export function hasPermission(
    permission: PermissionName,
    granted: readonly PermissionName[],
): boolean {
    return granted.includes(permission);
}

export function hasPermissions(
    permissions: readonly PermissionName[],
    granted: readonly PermissionName[],
    mode: PermissionCheckMode = 'all',
): boolean {
    if (mode === 'any') {
        return permissions.some((permission) => hasPermission(permission, granted));
    }

    return permissions.every((permission) => hasPermission(permission, granted));
}
