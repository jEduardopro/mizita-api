import { useTranslation } from 'react-i18next';
import { SettingsDialog } from '@/components/admin/settings/SettingsDialog';
import type { AssignableStaffRole, StaffProfileDetails, UpdateTeamMemberPayload } from '../types';
import { ProfileDetailsForm } from './ProfileDetailsForm';
import { ProfileDialogIdentity } from './ProfileDialogIdentity';
import {
    PANE_ICONS,
    PANE_LABEL_KEYS,
    type StaffProfilePane,
    type StaffProfilePaneContent,
} from './profile-panes';

const PROFILE_PANE: StaffProfilePane = 'profile';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    pane: StaffProfilePane;
    onPaneChange: (pane: StaffProfilePane) => void;
    profile: StaffProfileDetails;
    editableLevel?: AssignableStaffRole;
    onSaveProfile: (payload: UpdateTeamMemberPayload) => Promise<unknown>;
    onUploadPhoto: (photo: File) => Promise<unknown>;
    onRemovePhoto: () => Promise<unknown>;
    panes: readonly StaffProfilePaneContent[];
};

export function StaffProfileDialog({
    open,
    onOpenChange,
    title,
    pane,
    onPaneChange,
    profile,
    editableLevel,
    onSaveProfile,
    onUploadPhoto,
    onRemovePhoto,
    panes,
}: Props) {
    const { t } = useTranslation('admin');

    const paneIds: StaffProfilePane[] = [PROFILE_PANE, ...panes.map((supplementary) => supplementary.id)];

    const items = paneIds.map((id) => ({
        id,
        label: t(PANE_LABEL_KEYS[id]),
        icon: PANE_ICONS[id],
    }));

    const supplementaryPane = panes.find((supplementary) => supplementary.id === pane);

    return (
        <SettingsDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            closeLabel={t('profile.dialog.close')}
            navLabel={t('profile.dialog.nav')}
            identity={
                <ProfileDialogIdentity
                    name={profile.name}
                    jobTitle={profile.job_title}
                    photoUrl={profile.photo_url}
                    onUploadPhoto={onUploadPhoto}
                    onRemovePhoto={onRemovePhoto}
                />
            }
            items={items}
            activeId={supplementaryPane === undefined ? PROFILE_PANE : pane}
            onActiveChange={onPaneChange}
        >
            {supplementaryPane === undefined ? (
                <section aria-label={t(PANE_LABEL_KEYS.profile)} className="flex min-h-0 flex-1 flex-col">
                    <ProfileDetailsForm
                        profile={profile}
                        email={profile.email}
                        editableLevel={editableLevel}
                        onSave={onSaveProfile}
                        onCancel={() => onOpenChange(false)}
                    />
                </section>
            ) : (
                supplementaryPane.content
            )}
        </SettingsDialog>
    );
}
