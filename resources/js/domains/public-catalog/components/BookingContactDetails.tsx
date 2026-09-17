import { Phone } from 'lucide-react';
import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import type { SocialIconProps } from '@/components/public/shell/SocialIcons';
import type { PublicLink } from '../types';
import { linkLabelFor, PLATFORM_ICONS, socialLinksFrom, websiteLinkFrom } from './booking-links';
import { BookingSocialLinks } from './BookingSocialLinks';

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
    links: PublicLink[];
};

export function BookingContactDetails({ phone, links }: Props) {
    const { t } = useTranslation('public');

    const entries = contactEntriesFrom(phone, websiteLinkFrom(links));
    const socialLinks = socialLinksFrom(links);

    if (entries.length === 0 && socialLinks.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-8">
            {entries.length === 0 ? null : (
                <div className="grid gap-6 sm:grid-cols-[repeat(auto-fit,minmax(13rem,1fr))] sm:gap-10">
                    <div className="grid content-start gap-2">
                        <h3 className="text-sm font-medium text-balance">
                            {t('booking.contact.title')}
                        </h3>

                        <ul className="grid">
                            {entries.map((entry) => (
                                <li key={entry.key}>
                                    <a
                                        href={entry.href}
                                        {...entry.anchorProps}
                                        className="flex min-h-11 items-center gap-2 rounded-lg text-sm underline decoration-muted-foreground/50 underline-offset-4 transition-colors outline-none hover:decoration-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                                    >
                                        <entry.Icon
                                            aria-hidden="true"
                                            className="size-4 shrink-0 text-muted-foreground"
                                        />

                                        <span className="min-w-0 break-words">{entry.label}</span>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}

            {socialLinks.length === 0 ? null : (
                <div className="grid justify-items-start gap-2">
                    <h3 className="text-sm font-medium text-balance">
                        {t('booking.contact.social')}
                    </h3>

                    <BookingSocialLinks links={socialLinks} />
                </div>
            )}
        </div>
    );
}
