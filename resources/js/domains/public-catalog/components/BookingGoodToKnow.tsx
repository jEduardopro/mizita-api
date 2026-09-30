import { CalendarCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ABOUT_ENTRY_CLASSES } from './booking-about-entry';
import { BookingAboutColumn } from './BookingAboutColumn';
import { BookingPolicyDialog } from './BookingPolicyDialog';

type Props = {
    cancellationWindowMinutes: number | null;
    themeScope: string | undefined;
};

export function BookingGoodToKnow({ cancellationWindowMinutes, themeScope }: Props) {
    const { t } = useTranslation('public');

    return (
        <BookingAboutColumn title={t('booking.policy.goodToKnow')}>
            <ul className="grid">
                <li>
                    <BookingPolicyDialog
                        cancellationWindowMinutes={cancellationWindowMinutes}
                        themeScope={themeScope}
                    >
                        <button type="button" className={ABOUT_ENTRY_CLASSES}>
                            <CalendarCheck
                                aria-hidden="true"
                                className="size-4 shrink-0 text-muted-foreground"
                            />

                            <span className="min-w-0 break-words">{t('booking.policy.open')}</span>
                        </button>
                    </BookingPolicyDialog>
                </li>
            </ul>
        </BookingAboutColumn>
    );
}
