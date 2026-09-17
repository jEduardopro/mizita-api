import { Globe } from 'lucide-react';
import type { ReactNode } from 'react';
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

export const WEBSITE_PLATFORM: LinkPlatform = 'website';

export const PLATFORM_ICONS = {
    website: Globe,
    instagram: InstagramIcon,
    facebook: FacebookIcon,
    tiktok: TiktokIcon,
    x: TwitterIcon,
    linkedin: LinkedinIcon,
    youtube: YoutubeIcon,
    whatsapp: WhatsappIcon,
} as const satisfies Record<LinkPlatform, (props: SocialIconProps) => ReactNode>;

export const PLATFORM_LABEL_KEYS = {
    website: 'booking.links.platforms.website',
    instagram: 'booking.links.platforms.instagram',
    facebook: 'booking.links.platforms.facebook',
    tiktok: 'booking.links.platforms.tiktok',
    x: 'booking.links.platforms.x',
    linkedin: 'booking.links.platforms.linkedin',
    youtube: 'booking.links.platforms.youtube',
    whatsapp: 'booking.links.platforms.whatsapp',
} as const satisfies Record<LinkPlatform, string>;

export const PILL_CLASSES =
    'flex h-11 items-center gap-2 rounded-full border border-border px-4 text-sm font-medium transition-colors outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50';

export function linkLabelFor(url: string): string {
    return url.replace(/^https?:\/\//, '').replace(/\/$/, '');
}

export function websiteLinkFrom(links: PublicLink[]): PublicLink | null {
    return links.find((link) => link.platform === WEBSITE_PLATFORM) ?? null;
}

export function socialLinksFrom(links: PublicLink[]): PublicLink[] {
    return links.filter((link) => link.platform !== WEBSITE_PLATFORM);
}
