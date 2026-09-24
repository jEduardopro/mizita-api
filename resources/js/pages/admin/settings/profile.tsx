import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPane } from '@/components/admin/settings/SettingsPane';
import { ManageAccountPane } from '@/domains/accounts/components/ManageAccountPane';
import { SignInSecurityPane } from '@/domains/accounts/components/SignInSecurityPane';
import { BusinessHoursNotice } from '@/domains/availability/components/BusinessHoursNotice';
import { WorkingHoursPanel } from '@/domains/availability/components/WorkingHoursPanel';
import { WorkingHoursSummary } from '@/domains/availability/components/WorkingHoursSummary';
import { useMySchedule, useReplaceMySchedule } from '@/domains/availability/queries';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { useBusinessSettings, useMyBusinesses } from '@/domains/businesses/queries';
import { ManagedStaffServicesTab } from '@/domains/services/components/ManagedStaffServicesTab';
import { StaffServicesTab } from '@/domains/services/components/StaffServicesTab';
import { ProfileLoadError } from '@/domains/staff/components/ProfileLoadError';
import type { StaffProfilePane } from '@/domains/staff/components/profile-panes';
import { ROLE_LABEL_KEYS } from '@/domains/staff/components/profile-role';
import { StaffProfileDialog } from '@/domains/staff/components/StaffProfileDialog';
import { StaffProfileSkeleton } from '@/domains/staff/components/StaffProfileSkeleton';
import { StaffProfileView } from '@/domains/staff/components/StaffProfileView';
import {
    useAttachMyProfilePhoto,
    useMyProfile,
    useRefreshMyProfile,
    useRemoveMyProfilePhoto,
    useUpdateMyProfile,
} from '@/domains/staff/queries';
import type { MyProfile } from '@/domains/staff/types';
import { useAuthorization } from '@/hooks/use-authorization';
import { AdminLayout } from '@/layouts/AdminLayout';
import { isNotFoundError } from '@/lib/http';

type DialogState = {
    open: boolean;
    pane: StaffProfilePane;
};

type ScreenProps = {
    profile: MyProfile;
};

function MyProfileScreen({ profile }: ScreenProps) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const schedule = useMySchedule();
    const replaceSchedule = useReplaceMySchedule();
    const { data: businesses } = useMyBusinesses();
    const { data: businessSettings } = useBusinessSettings();
    const updateProfile = useUpdateMyProfile();
    const attachPhoto = useAttachMyProfilePhoto();
    const removePhoto = useRemoveMyProfilePhoto();
    const refreshProfile = useRefreshMyProfile();
    const [dialog, setDialog] = useState<DialogState>({ open: false, pane: 'profile' });

    const timezone = businessSettings?.timezone ?? businesses?.[0]?.timezone ?? null;
    const businessSchedule = schedule.data?.inherited
        ? schedule.data.schedule
        : (businessSettings?.schedule ?? null);

    const openDialog = (pane: StaffProfilePane) => setDialog({ open: true, pane });
    const closeDialog = () => setDialog((current) => ({ ...current, open: false }));
    const retrySchedule = () => void schedule.refetch();

    const hoursNotice = can('view_business_settings') ? (
        <BusinessHoursNotice businessSettingsHref={BRAND_SETTINGS_URL} />
    ) : null;

    const hoursPanel = (onCancel?: () => void) => (
        <WorkingHoursPanel
            schedule={schedule.data}
            loadFailed={schedule.isError}
            onRetry={retrySchedule}
            businessSchedule={businessSchedule}
            onSave={replaceSchedule.mutateAsync}
            onCancel={onCancel}
            notice={hoursNotice}
        />
    );

    return (
        <>
            <StaffProfileView
                profile={profile}
                onEdit={openDialog}
                hoursSummary={
                    <WorkingHoursSummary
                        schedule={schedule.data?.schedule}
                        loadFailed={schedule.isError}
                        onRetry={retrySchedule}
                        timezone={timezone}
                        onEdit={() => openDialog('hours')}
                    />
                }
                services={
                    can('edit_service') ? (
                        <ManagedStaffServicesTab staffMemberId={profile.staff_member_id} />
                    ) : (
                        <StaffServicesTab staffMemberId={profile.staff_member_id} />
                    )
                }
                hours={hoursPanel()}
            />

            <StaffProfileDialog
                open={dialog.open}
                onOpenChange={(open) => setDialog((current) => ({ ...current, open }))}
                pane={dialog.pane}
                onPaneChange={openDialog}
                profile={profile}
                onSaveProfile={updateProfile.mutateAsync}
                onUploadPhoto={attachPhoto.mutateAsync}
                onRemovePhoto={() => removePhoto.mutateAsync()}
                hoursPane={<SettingsPane title={t('workingHours.title')}>{hoursPanel(closeDialog)}</SettingsPane>}
                securityPane={
                    <SignInSecurityPane
                        email={profile.email}
                        hasPassword={profile.has_password}
                        roleLabel={t(ROLE_LABEL_KEYS[profile.role])}
                        onPasswordSaved={refreshProfile}
                    />
                }
                accountPane={<ManageAccountPane />}
            />
        </>
    );
}

export default function Profile() {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const profile = useMyProfile();

    const breadcrumbs = can('view_business_settings')
        ? [{ label: t('nav.settings'), href: BRAND_SETTINGS_URL }, { label: t('nav.yourProfile') }]
        : undefined;

    return (
        <AdminLayout title={t('profile.title')} description={t('profile.description')} breadcrumbs={breadcrumbs}>
            {profile.data ? <MyProfileScreen profile={profile.data} /> : null}

            {profile.isPending ? <StaffProfileSkeleton /> : null}

            {profile.isError && ! profile.data ? (
                <ProfileLoadError
                    notFound={isNotFoundError(profile.error)}
                    onRetry={() => void profile.refetch()}
                />
            ) : null}
        </AdminLayout>
    );
}
