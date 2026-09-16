import { Globe } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    FacebookIcon,
    InstagramIcon,
    LinkedinIcon,
    TiktokIcon,
    TwitterIcon,
    WhatsappIcon,
    YoutubeIcon,
    type SocialIconProps,
} from '@/components/public/shell/SocialIcons';
import type { LinkPlatform } from '@/lib/booking-brand';
import type { PublicLink } from '../types';

const PLATFORM_ICONS = {
    website: Globe,
    instagram: InstagramIcon,
    facebook: FacebookIcon,
    tiktok: TiktokIcon,
    x: TwitterIcon,
    linkedin: LinkedinIcon,
    youtube: YoutubeIcon,
    whatsapp: WhatsappIcon,
} as const satisfies Record<LinkPlatform, (props: SocialIconProps) => ReactNode>;

const PLATFORM_LABEL_KEYS = {
    website: 'booking.links.platforms.website',
    instagram: 'booking.links.platforms.instagram',
    facebook: 'booking.links.platforms.facebook',
    tiktok: 'booking.links.platforms.tiktok',
    x: 'booking.links.platforms.x',
    linkedin: 'booking.links.platforms.linkedin',
    youtube: 'booking.links.platforms.youtube',
    whatsapp: 'booking.links.platforms.whatsapp',
} as const satisfies Record<LinkPlatform, string>;

type Props = {
    links: PublicLink[];
};

export function BookingLinks({ links }: Props) {
    const { t } = useTranslation('public');

    return (
        <ul aria-label={t('booking.links.title')} className="flex flex-wrap gap-2">
            {links.map((link) => {
                const Icon = PLATFORM_ICONS[link.platform];

                return (
                    <li key={link.platform}>
                        <a
                            href={link.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex h-11 items-center gap-2 rounded-full border border-border px-4 text-sm font-medium transition-colors outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <Icon className="size-4 shrink-0 text-muted-foreground" />
                            {t(PLATFORM_LABEL_KEYS[link.platform])}
                        </a>
                    </li>
                );
            })}
        </ul>
    );
}
