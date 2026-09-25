import { Clock, Lock, UserRound, Wrench, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export const STAFF_PROFILE_PANES = ['profile', 'hours', 'security', 'account'] as const;

export type StaffProfilePane = (typeof STAFF_PROFILE_PANES)[number];

export type SupplementaryProfilePane = Exclude<StaffProfilePane, 'profile'>;

export type StaffProfilePaneContent = {
    id: SupplementaryProfilePane;
    content: ReactNode;
};

export const PANE_LABEL_KEYS = {
    profile: 'profile.dialog.panes.profile',
    hours: 'profile.dialog.panes.hours',
    security: 'profile.dialog.panes.security',
    account: 'profile.dialog.panes.account',
} as const satisfies Record<StaffProfilePane, string>;

export const PANE_ICONS: Record<StaffProfilePane, LucideIcon> = {
    profile: UserRound,
    hours: Clock,
    security: Lock,
    account: Wrench,
};

export function staffProfilePaneFrom(value: string | null): StaffProfilePane | undefined {
    return STAFF_PROFILE_PANES.find((pane) => pane === value);
}
