import { cn } from 'cn';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    BUTTON_SHAPE_CLASSES,
    type BrandColorClasses,
    type ButtonShape,
} from '@/lib/booking-brand';
import { initialsFrom } from '@/lib/initials';
import type { PublicTeamMember } from '../../types';

type Props = {
    member: PublicTeamMember;
    isSelected: boolean;
    accent: BrandColorClasses;
    buttonShape: ButtonShape;
    onSelect(staffId: string): void;
};

export function BookingStaffCard({ member, isSelected, accent, buttonShape, onSelect }: Props) {
    const { t } = useTranslation('public');
    const nameId = useId();

    return (
        <li
            className={cn(
                'grid justify-items-center gap-3 rounded-2xl border border-border p-5 text-center',
                isSelected && accent.surface,
            )}
        >
            <Avatar className="size-14">
                <AvatarFallback
                    className={cn(
                        'text-base font-medium',
                        isSelected
                            ? cn(accent.accent, accent.accentForeground)
                            : cn(accent.surface, 'text-foreground'),
                    )}
                >
                    {initialsFrom(member.name)}
                </AvatarFallback>
            </Avatar>

            <span
                id={nameId}
                className="min-w-0 text-[0.9375rem] leading-snug font-medium text-balance"
            >
                {member.name}
            </span>

            <button
                type="button"
                onClick={() => onSelect(member.id)}
                aria-describedby={nameId}
                aria-current={isSelected ? true : undefined}
                className={cn(
                    'flex h-11 w-full items-center justify-center px-5 text-base font-medium outline-none motion-safe:transition-opacity hover:opacity-90 focus-visible:ring-3 focus-visible:ring-ring/50',
                    accent.accent,
                    accent.accentForeground,
                    BUTTON_SHAPE_CLASSES[buttonShape],
                )}
            >
                {t('booking.cta.book')}
            </button>
        </li>
    );
}
