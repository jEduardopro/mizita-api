import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthorization } from '@/hooks/use-authorization';
import type { BookingLinkBlocker } from '../types';
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
    slug: string | null;
    url: string | null;
    blockers: readonly BookingLinkBlocker[];
};

export function StaffBookingLink({ staffMemberId, slug, url, blockers }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const canEdit = can('edit_staff_member');

    if (slug !== null && url !== null) {
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
