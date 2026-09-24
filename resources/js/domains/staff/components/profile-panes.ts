import { Clock, Lock, UserRound, Wrench, type LucideIcon } from 'lucide-react';

export const STAFF_PROFILE_PANES = ['profile', 'hours', 'security', 'account'] as const;

export type StaffProfilePane = (typeof STAFF_PROFILE_PANES)[number];

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
