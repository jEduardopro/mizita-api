import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthorization } from '@/hooks/use-authorization';
import { useIsTeamMemberPaused } from '@/hooks/use-is-team-member-paused';
import type { BookingLinkBlocker, StaffRole } from '../types';
import { bookingLinkBlockerKey } from './booking-link';
import { EditBookingSlugDialog } from './EditBookingSlugDialog';
import { GenerateBookingLinkAction } from './GenerateBookingLinkAction';
import { StaffBookingLinkValue } from './StaffBookingLinkValue';
import { useCopyBookingLink } from './use-copy-booking-link';

type IssuedLinkProps = {
    staffMemberId: string;
    slug: string;
    url: string;
    canEdit: boolean;
};

function IssuedBookingLink({ staffMemberId, slug, url, canEdit }: IssuedLinkProps) {
    const [isEditing, setIsEditing] = useState(false);
    const copy = useCopyBookingLink();

    return (
        <>
            <StaffBookingLinkValue
                url={url}
                onCopy={() => copy(url)}
                onEdit={canEdit ? () => setIsEditing(true) : undefined}
            />

            {canEdit ? (
                <EditBookingSlugDialog
                    staffMemberId={staffMemberId}
                    slug={slug}
                    url={url}
                    open={isEditing}
                    onOpenChange={setIsEditing}
                />
            ) : null}
        </>
    );
}

type Props = {
    staffMemberId: string;
    role: StaffRole;
    slug: string | null;
    url: string | null;
    blockers: readonly BookingLinkBlocker[];
};

export function StaffBookingLink({ staffMemberId, role, slug, url, blockers }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const isPaused = useIsTeamMemberPaused();
    const paused = isPaused(role);
    const canEdit = can('edit_staff_member') && ! paused;

    if (! paused && slug !== null && url !== null) {
        return <IssuedBookingLink staffMemberId={staffMemberId} slug={slug} url={url} canEdit={canEdit} />;
    }

    if (! canEdit) {
        return <span className="text-muted-foreground">{t('profile.bookingLink.none')}</span>;
    }

    if (blockers.length > 0) {
        return <span className="text-pretty text-muted-foreground">{t(bookingLinkBlockerKey(blockers))}</span>;
    }

    return <GenerateBookingLinkAction staffMemberId={staffMemberId} />;
}
