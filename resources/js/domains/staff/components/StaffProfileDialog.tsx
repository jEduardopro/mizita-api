import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsDialog } from '@/components/admin/settings/SettingsDialog';
import type { StaffProfileDetails, UpdateMyProfilePayload } from '../types';
import { ProfileDetailsForm } from './ProfileDetailsForm';
import { ProfileDialogIdentity } from './ProfileDialogIdentity';
import { PANE_ICONS, PANE_LABEL_KEYS, STAFF_PROFILE_PANES, type StaffProfilePane } from './profile-panes';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pane: StaffProfilePane;
    onPaneChange: (pane: StaffProfilePane) => void;
    profile: StaffProfileDetails;
    onSaveProfile: (payload: UpdateMyProfilePayload) => Promise<unknown>;
    onUploadPhoto: (photo: File) => Promise<unknown>;
    onRemovePhoto: () => Promise<unknown>;
    hoursPane: ReactNode;
    securityPane: ReactNode;
    accountPane: ReactNode;
};

export function StaffProfileDialog({
    open,
    onOpenChange,
    pane,
    onPaneChange,
    profile,
    onSaveProfile,
    onUploadPhoto,
    onRemovePhoto,
    hoursPane,
    securityPane,
    accountPane,
}: Props) {
    const { t } = useTranslation('admin');

    const items = STAFF_PROFILE_PANES.map((id) => ({
        id,
        label: t(PANE_LABEL_KEYS[id]),
        icon: PANE_ICONS[id],
    }));

    const panes: Record<StaffProfilePane, ReactNode> = {
        profile: (
            <section aria-label={t(PANE_LABEL_KEYS.profile)} className="flex min-h-0 flex-1 flex-col">
                <ProfileDetailsForm
                    profile={profile}
                    email={profile.email}
                    onSave={onSaveProfile}
                    onCancel={() => onOpenChange(false)}
                />
            </section>
        ),
        hours: hoursPane,
        security: securityPane,
        account: accountPane,
    };

    return (
        <SettingsDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('profile.dialog.title')}
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
            activeId={pane}
            onActiveChange={onPaneChange}
        >
            {panes[pane]}
        </SettingsDialog>
    );
}
