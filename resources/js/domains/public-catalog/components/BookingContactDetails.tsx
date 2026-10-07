import { Phone } from 'lucide-react';
import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import type { SocialIconProps } from '@/components/shared/SocialIcons';
import type { PublicLink } from '../types';
import { ABOUT_ENTRY_CLASSES } from './booking-about-entry';
import { linkLabelFor, PLATFORM_ICONS } from './booking-links';
import { BookingAboutColumn } from './BookingAboutColumn';

const EXTERNAL_ANCHOR_PROPS = {
    target: '_blank',
    rel: 'noopener noreferrer',
} as const;

type ContactEntry = {
    key: string;
    href: string;
    label: string;
    Icon: (props: SocialIconProps) => ReactNode;
    anchorProps?: AnchorHTMLAttributes<HTMLAnchorElement>;
};

function contactEntriesFrom(phone: string | null, website: PublicLink | null): ContactEntry[] {
    return [
        phone === null
            ? null
            : { key: 'phone', href: `tel:${phone}`, label: phone, Icon: Phone },
        website === null
            ? null
            : {
                  key: 'website',
                  href: website.url,
                  label: linkLabelFor(website.url),
                  Icon: PLATFORM_ICONS.website,
                  anchorProps: EXTERNAL_ANCHOR_PROPS,
              },
    ].filter((entry) => entry !== null);
}

type Props = {
    phone: string | null;
    website: PublicLink | null;
};

export function BookingContactDetails({ phone, website }: Props) {
    const { t } = useTranslation('public');

    const entries = contactEntriesFrom(phone, website);

    if (entries.length === 0) {
        return null;
    }

    return (
        <BookingAboutColumn title={t('booking.contact.title')}>
            <ul className="grid">
                {entries.map((entry) => (
                    <li key={entry.key}>
                        <a href={entry.href} {...entry.anchorProps} className={ABOUT_ENTRY_CLASSES}>
                            <entry.Icon
                                aria-hidden="true"
                                className="size-4 shrink-0 text-muted-foreground"
                            />

                            <span className="min-w-0 break-words">{entry.label}</span>
                        </a>
                    </li>
                ))}
            </ul>
        </BookingAboutColumn>
    );
}
