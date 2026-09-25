import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { StaffProfileDetails } from '../types';
import { ProfileAboutPanel } from './ProfileAboutPanel';
import { ProfileHeader } from './ProfileHeader';
import type { StaffProfilePane } from './profile-panes';
import { ROLE_LABEL_KEYS } from './profile-role';

const PROFILE_TABS = ['about', 'services', 'hours'] as const;

type ProfileTab = (typeof PROFILE_TABS)[number];

const TAB_LABEL_KEYS = {
    about: 'profile.tabs.about',
    services: 'profile.tabs.services',
    hours: 'profile.tabs.hours',
} as const satisfies Record<ProfileTab, string>;

function profileTabFrom(value: string): ProfileTab {
    return PROFILE_TABS.find((tab) => tab === value) ?? 'about';
}

type Props = {
    profile: StaffProfileDetails;
    onEdit?: (pane: StaffProfilePane) => void;
    hoursSummary: ReactNode;
    services: ReactNode;
    hours?: ReactNode;
};

export function StaffProfileView({ profile, onEdit, hoursSummary, services, hours }: Props) {
    const { t } = useTranslation('admin');
    const [tab, setTab] = useState<ProfileTab>('about');

    const editProfile = onEdit === undefined ? undefined : () => onEdit('profile');
    const visibleTabs = PROFILE_TABS.filter((profileTab) => profileTab !== 'hours' || hours !== undefined);

    const panels: Record<ProfileTab, ReactNode> = {
        about: (
            <ProfileAboutPanel
                phone={profile.phone}
                email={profile.email}
                about={profile.about}
                roleLabel={t(ROLE_LABEL_KEYS[profile.role])}
                hoursSummary={hoursSummary}
                onAddPhone={editProfile}
                onAddAbout={editProfile}
            />
        ),
        services,
        hours: (
            <div className="-mx-5 flex max-w-2xl flex-col sm:mx-0 sm:rounded-xl sm:border sm:border-border sm:pt-4">
                {hours}
            </div>
        ),
    };

    return (
        <div className="grid gap-6">
            <ProfileHeader
                name={profile.name}
                jobTitle={profile.job_title}
                photoUrl={profile.photo_url}
                onEdit={editProfile}
            />

            <Tabs value={tab} onValueChange={(next) => setTab(profileTabFrom(next))} className="gap-5">
                <TabsList
                    variant="line"
                    className="-mx-5 flex w-auto justify-start gap-4 overflow-x-auto border-b border-border px-5 pb-[5px] [scrollbar-width:none] group-data-horizontal/tabs:h-auto sm:mx-0 sm:w-full sm:px-0 [&::-webkit-scrollbar]:hidden"
                >
                    {visibleTabs.map((profileTab) => (
                        <TabsTrigger
                            key={profileTab}
                            value={profileTab}
                            className="h-auto min-h-11 flex-none px-1"
                        >
                            {t(TAB_LABEL_KEYS[profileTab])}
                        </TabsTrigger>
                    ))}
                </TabsList>

                {visibleTabs.map((profileTab) => (
                    <TabsContent key={profileTab} value={profileTab}>
                        {panels[profileTab]}
                    </TabsContent>
                ))}
            </Tabs>
        </div>
    );
}
