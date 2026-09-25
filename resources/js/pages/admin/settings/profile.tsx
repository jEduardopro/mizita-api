import { useTranslation } from 'react-i18next';
import { ManageAccountPane } from '@/domains/accounts/components/ManageAccountPane';
import { SignInSecurityPane } from '@/domains/accounts/components/SignInSecurityPane';
import { BusinessHoursNotice } from '@/domains/availability/components/BusinessHoursNotice';
import { businessScheduleFor } from '@/domains/availability/components/business-schedule';
import { WorkingHoursPanel } from '@/domains/availability/components/WorkingHoursPanel';
import { WorkingHoursSummary } from '@/domains/availability/components/WorkingHoursSummary';
import { useMySchedule, useReplaceMySchedule } from '@/domains/availability/queries';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { useBusinessTimezone, useCalendarSettings } from '@/domains/businesses/queries';
import { StaffServicesSection } from '@/domains/services/components/StaffServicesSection';
import { ProfileLoadError } from '@/domains/staff/components/ProfileLoadError';
import { ROLE_LABEL_KEYS } from '@/domains/staff/components/profile-role';
import { StaffProfileScreen } from '@/domains/staff/components/StaffProfileScreen';
import { StaffProfileSkeleton } from '@/domains/staff/components/StaffProfileSkeleton';
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

type ScreenProps = {
    profile: MyProfile;
};

function MyProfileScreen({ profile }: ScreenProps) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const schedule = useMySchedule();
    const replaceSchedule = useReplaceMySchedule();
    const { data: calendarSettings } = useCalendarSettings();
    const timezone = useBusinessTimezone();
    const updateProfile = useUpdateMyProfile();
    const attachPhoto = useAttachMyProfilePhoto();
    const removePhoto = useRemoveMyProfilePhoto();
    const refreshProfile = useRefreshMyProfile();

    const businessSchedule = businessScheduleFor(schedule.data, calendarSettings?.schedule);
    const retrySchedule = () => void schedule.refetch();

    const hoursNotice = can('view_business_settings') ? (
        <BusinessHoursNotice businessSettingsHref={BRAND_SETTINGS_URL} />
    ) : null;

    return (
        <StaffProfileScreen
            profile={profile}
            dialogTitle={t('profile.dialog.title')}
            onSaveProfile={updateProfile.mutateAsync}
            onUploadPhoto={attachPhoto.mutateAsync}
            onRemovePhoto={() => removePhoto.mutateAsync()}
            services={<StaffServicesSection staffMemberId={profile.staff_member_id} />}
            renderHoursSummary={(onEdit) => (
                <WorkingHoursSummary
                    schedule={schedule.data?.schedule}
                    loadFailed={schedule.isError}
                    onRetry={retrySchedule}
                    timezone={timezone}
                    onEdit={onEdit}
                />
            )}
            renderHoursPanel={(onCancel) => (
                <WorkingHoursPanel
                    schedule={schedule.data}
                    loadFailed={schedule.isError}
                    onRetry={retrySchedule}
                    businessSchedule={businessSchedule}
                    onSave={replaceSchedule.mutateAsync}
                    onCancel={onCancel}
                    notice={hoursNotice}
                />
            )}
            accountPanes={[
                {
                    id: 'security',
                    content: (
                        <SignInSecurityPane
                            email={profile.email}
                            hasPassword={profile.has_password}
                            roleLabel={t(ROLE_LABEL_KEYS[profile.role])}
                            onPasswordSaved={refreshProfile}
                        />
                    ),
                },
                { id: 'account', content: <ManageAccountPane /> },
            ]}
        />
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
        <AdminLayout title={t('profile.title')} breadcrumbs={breadcrumbs}>
            {profile.data ? <MyProfileScreen profile={profile.data} /> : null}

            {profile.isPending ? <StaffProfileSkeleton /> : null}

            {profile.isError && ! profile.data ? (
                <ProfileLoadError
                    notFound={isNotFoundError(profile.error)}
                    onRetry={() => void profile.refetch()}
                    messages={{
                        notFoundTitle: t('profile.notFound.title'),
                        notFoundBody: t('profile.notFound.body'),
                        loadErrorTitle: t('profile.loadError.title'),
                    }}
                />
            ) : null}
        </AdminLayout>
    );
}
