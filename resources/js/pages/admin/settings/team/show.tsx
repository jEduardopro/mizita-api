import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BusinessHoursNotice } from '@/domains/availability/components/BusinessHoursNotice';
import { businessScheduleFor } from '@/domains/availability/components/business-schedule';
import { WorkingHoursPanel } from '@/domains/availability/components/WorkingHoursPanel';
import { WorkingHoursSummary } from '@/domains/availability/components/WorkingHoursSummary';
import { useReplaceStaffSchedule, useStaffSchedule } from '@/domains/availability/queries';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { useBusinessTimezone, useCalendarSettings } from '@/domains/businesses/queries';
import { StaffServicesSection } from '@/domains/services/components/StaffServicesSection';
import { ProfileLoadError } from '@/domains/staff/components/ProfileLoadError';
import { staffProfilePaneFrom, type StaffProfilePane } from '@/domains/staff/components/profile-panes';
import { StaffProfileScreen } from '@/domains/staff/components/StaffProfileScreen';
import { StaffProfileSkeleton } from '@/domains/staff/components/StaffProfileSkeleton';
import { editableLevelOf, staffProfileDetailsFrom } from '@/domains/staff/components/team-member-profile';
import { EDIT_PANE_PARAMETER, TEAM_SETTINGS_URL } from '@/domains/staff/components/team-urls';
import { useStaffProfileAccess } from '@/domains/staff/components/use-staff-profile-access';
import {
    useAttachTeamMemberPhoto,
    useMyProfile,
    useRemoveTeamMemberPhoto,
    useTeamMember,
    useUpdateTeamMember,
} from '@/domains/staff/queries';
import type { MyProfile, TeamMember } from '@/domains/staff/types';
import { useAuthorization } from '@/hooks/use-authorization';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { AdminLayout } from '@/layouts/AdminLayout';
import { isNotFoundError } from '@/lib/http';
import { MyProfileScreen } from '@/pages/admin/settings/profile';

type ScreenProps = {
    member: TeamMember;
    initialPane: StaffProfilePane | undefined;
    readOnly: boolean;
};

function TeamMemberScreen({ member, initialPane, readOnly }: ScreenProps) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const schedule = useStaffSchedule(member.id);
    const replaceSchedule = useReplaceStaffSchedule(member.id);
    const { data: calendarSettings } = useCalendarSettings();
    const timezone = useBusinessTimezone();
    const updateMember = useUpdateTeamMember();
    const attachPhoto = useAttachTeamMemberPhoto();
    const removePhoto = useRemoveTeamMemberPhoto();
    const profile = useMemo(() => staffProfileDetailsFrom(member), [member]);

    const businessSchedule = businessScheduleFor(schedule.data, calendarSettings?.schedule);
    const retrySchedule = () => void schedule.refetch();

    const hoursNotice = can('view_business_settings') ? (
        <BusinessHoursNotice businessSettingsHref={BRAND_SETTINGS_URL} />
    ) : null;

    return (
        <StaffProfileScreen
            profile={profile}
            dialogTitle={t('team.member.dialogTitle')}
            initialPane={initialPane}
            readOnly={readOnly}
            editableLevel={editableLevelOf(member)}
            onSaveProfile={(payload) => updateMember.mutateAsync({ id: member.id, payload })}
            onUploadPhoto={(photo) => attachPhoto.mutateAsync({ id: member.id, photo })}
            onRemovePhoto={() => removePhoto.mutateAsync(member.id)}
            services={<StaffServicesSection staffMemberId={member.id} />}
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
        />
    );
}

type ProfileProps = ScreenProps & {
    selfProfile: MyProfile | undefined;
};

function TeamMemberProfile({ selfProfile, ...screen }: ProfileProps) {
    if (selfProfile !== undefined) {
        return <MyProfileScreen profile={selfProfile} initialPane={screen.initialPane} />;
    }

    return <TeamMemberScreen {...screen} />;
}

type Props = {
    staffMemberId: string;
};

export default function ShowTeamMember({ staffMemberId }: Props) {
    const { t } = useTranslation('admin');
    const { read } = useUrlQueryState();
    const [initialPane] = useState(() => staffProfilePaneFrom(read(EDIT_PANE_PARAMETER)));
    const member = useTeamMember(staffMemberId);
    const myProfile = useMyProfile();
    const access = useStaffProfileAccess(staffMemberId);

    const selfProfile = access === 'self' ? myProfile.data : undefined;
    const title = selfProfile?.name ?? member.data?.name ?? t('team.member.title');
    const isResolvingAccess = member.data !== undefined && access === undefined;

    return (
        <AdminLayout
            title={title}
            breadcrumbs={[
                { label: t('nav.settings'), href: BRAND_SETTINGS_URL },
                { label: t('nav.team'), href: TEAM_SETTINGS_URL },
                { label: title },
            ]}
        >
            {member.data && access !== undefined ? (
                <TeamMemberProfile
                    member={member.data}
                    selfProfile={selfProfile}
                    initialPane={initialPane}
                    readOnly={access === 'view'}
                />
            ) : null}

            {member.isPending || isResolvingAccess ? <StaffProfileSkeleton /> : null}

            {member.isError && ! member.data ? (
                <ProfileLoadError
                    notFound={isNotFoundError(member.error)}
                    onRetry={() => void member.refetch()}
                    messages={{
                        notFoundTitle: t('team.member.notFound.title'),
                        notFoundBody: t('team.member.notFound.body'),
                        loadErrorTitle: t('team.member.loadError.title'),
                    }}
                />
            ) : null}
        </AdminLayout>
    );
}
