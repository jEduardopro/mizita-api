import { useTranslation } from 'react-i18next';
import type { PublicLink } from '../types';
import { socialLinksFrom, websiteLinkFrom } from './booking-links';
import { BookingAboutColumn } from './BookingAboutColumn';
import { BookingContactDetails } from './BookingContactDetails';
import { BookingGoodToKnow } from './BookingGoodToKnow';
import { BookingSocialLinks } from './BookingSocialLinks';

type Props = {
    about: string;
    phone: string | null;
    links: PublicLink[];
    cancellationWindowMinutes: number | null;
    themeScope: string | undefined;
};

export function BookingAbout({
    about,
    phone,
    links,
    cancellationWindowMinutes,
    themeScope,
}: Props) {
    const { t } = useTranslation('public');

    const socialLinks = socialLinksFrom(links);

    return (
        <div className="grid gap-8">
            {about === '' ? null : (
                <p className="max-w-2xl text-[0.9375rem] leading-relaxed whitespace-pre-line text-pretty text-muted-foreground">
                    {about}
                </p>
            )}

            <div className="grid gap-6 sm:grid-cols-[repeat(auto-fit,minmax(13rem,1fr))] sm:gap-10">
                <BookingContactDetails phone={phone} website={websiteLinkFrom(links)} />

                <BookingGoodToKnow
                    cancellationWindowMinutes={cancellationWindowMinutes}
                    themeScope={themeScope}
                />
            </div>

            {socialLinks.length === 0 ? null : (
                <BookingAboutColumn title={t('booking.contact.social')}>
                    <BookingSocialLinks links={socialLinks} />
                </BookingAboutColumn>
            )}
        </div>
    );
}
