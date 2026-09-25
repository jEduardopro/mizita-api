export type PermissionName =
    | 'manage_business'
    | 'view_staff_members'
    | 'create_staff_member'
    | 'edit_staff_member'
    | 'delete_staff_member'
    | 'reveal_temporary_password'
    | 'manage_all_calendars'
    | 'view_services'
    | 'create_service'
    | 'edit_service'
    | 'delete_service'
    | 'view_customers'
    | 'create_customer'
    | 'edit_customer'
    | 'delete_customer'
    | 'view_business_settings'
    | 'edit_business_settings'
    | 'view_appointments'
    | 'create_appointment'
    | 'edit_appointment'
    | 'delete_appointment'
    | 'view_payments'
    | 'create_payment'
    | 'void_payment_transaction'
    | 'manage_integrations';

export type RoleName = 'owner' | 'staff' | 'no_access';

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
