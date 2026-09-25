import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPane } from '@/components/admin/settings/SettingsPane';
import type { AssignableStaffRole, StaffProfileDetails, UpdateTeamMemberPayload } from '../types';
import type { StaffProfilePane, StaffProfilePaneContent } from './profile-panes';
import { StaffProfileDialog } from './StaffProfileDialog';
import { StaffProfileView } from './StaffProfileView';

type DialogState = {
    open: boolean;
    pane: StaffProfilePane;
};

const CLOSED_DIALOG: DialogState = { open: false, pane: 'profile' };

function initialDialogState(initialPane: StaffProfilePane | undefined): DialogState {
    return initialPane === undefined ? CLOSED_DIALOG : { open: true, pane: initialPane };
}

type Props = {
    profile: StaffProfileDetails;
    dialogTitle: string;
    initialPane?: StaffProfilePane;
    readOnly?: boolean;
    editableLevel?: AssignableStaffRole;
    onSaveProfile: (payload: UpdateTeamMemberPayload) => Promise<unknown>;
    onUploadPhoto: (photo: File) => Promise<unknown>;
    onRemovePhoto: () => Promise<unknown>;
    services: ReactNode;
    renderHoursSummary: (onEdit?: () => void) => ReactNode;
    renderHoursPanel: (onCancel?: () => void) => ReactNode;
    accountPanes?: readonly StaffProfilePaneContent[];
    headerActions?: ReactNode;
};

export function StaffProfileScreen({
    profile,
    dialogTitle,
    initialPane,
    readOnly = false,
    editableLevel,
    onSaveProfile,
    onUploadPhoto,
    onRemovePhoto,
    services,
    renderHoursSummary,
    renderHoursPanel,
    accountPanes = [],
    headerActions,
}: Props) {
    const { t } = useTranslation('admin');
    const [dialog, setDialog] = useState<DialogState>(() => initialDialogState(initialPane));

    if (readOnly) {
        return (
            <StaffProfileView
                profile={profile}
                hoursSummary={renderHoursSummary()}
                services={services}
                headerActions={headerActions}
            />
        );
    }

    const openDialog = (pane: StaffProfilePane) => setDialog({ open: true, pane });
    const closeDialog = () => setDialog((current) => ({ ...current, open: false }));

    const hoursPane: StaffProfilePaneContent = {
        id: 'hours',
        content: <SettingsPane title={t('workingHours.title')}>{renderHoursPanel(closeDialog)}</SettingsPane>,
    };

    return (
        <>
            <StaffProfileView
                profile={profile}
                onEdit={openDialog}
                hoursSummary={renderHoursSummary(() => openDialog('hours'))}
                services={services}
                hours={renderHoursPanel()}
                headerActions={headerActions}
            />

            <StaffProfileDialog
                open={dialog.open}
                onOpenChange={(open) => setDialog((current) => ({ ...current, open }))}
                title={dialogTitle}
                pane={dialog.pane}
                onPaneChange={openDialog}
                profile={profile}
                editableLevel={editableLevel}
                onSaveProfile={onSaveProfile}
                onUploadPhoto={onUploadPhoto}
                onRemovePhoto={onRemovePhoto}
                panes={[hoursPane, ...accountPanes]}
            />
        </>
    );
}
