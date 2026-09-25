import type { StaffProfilePane } from './profile-panes';

export const TEAM_SETTINGS_URL = '/settings/team';

export const EDIT_PANE_PARAMETER = 'edit';

export function teamMemberShowUrl(id: string): string {
    return `${TEAM_SETTINGS_URL}/${id}`;
}

export function teamMemberEditUrl(id: string, pane: StaffProfilePane): string {
    const query = new URLSearchParams({ [EDIT_PANE_PARAMETER]: pane });

    return `${teamMemberShowUrl(id)}?${query.toString()}`;
}
