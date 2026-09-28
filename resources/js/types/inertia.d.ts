import '@inertiajs/core';
import type { Appearance } from '@/lib/appearance';
import type { PermissionName, RoleName } from '@/lib/authorization';
import type { SharedPlan } from '@/lib/plan';

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: {
            name: string;
            status: string | null;
            locale: string;
            appearance: Appearance;
            sidebarOpen: boolean;
            supportedLocales: string[];
            flash: { error: string | null };
            auth: { roles: RoleName[]; permissions: PermissionName[] };
            plan: SharedPlan | null;
        };
    }
}
