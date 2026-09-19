import { UserRoundX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { BrandColorClasses, ButtonShape } from '@/lib/booking-brand';
import type { PublicTeamMember } from '../../types';
import { BookingStaffCard } from './BookingStaffCard';

type Props = {
    team: PublicTeamMember[];
    selectedStaffId: string | null;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    onSelect(staffId: string): void;
};

export function BookingStaffList({
    team,
    selectedStaffId,
    accent,
    buttonShape,
    onSelect,
}: Props) {
    const { t } = useTranslation('public');

    if (team.length === 0) {
        return (
            <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
                <UserRoundX aria-hidden="true" className="size-6 text-muted-foreground" />

                <p className="max-w-sm text-sm text-pretty text-muted-foreground">
                    {t('booking.flow.empty.staff')}
                </p>
            </div>
        );
    }

    return (
        <ul className="grid gap-3 sm:grid-cols-2">
            {team.map((member) => (
                <BookingStaffCard
                    key={member.id}
                    member={member}
                    isSelected={member.id === selectedStaffId}
                    accent={accent}
                    buttonShape={buttonShape}
                    onSelect={onSelect}
                />
            ))}
        </ul>
    );
}
