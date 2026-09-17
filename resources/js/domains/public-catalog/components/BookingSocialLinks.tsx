import { useTranslation } from 'react-i18next';
import type { PublicLink } from '../types';
import { PLATFORM_ICONS, PLATFORM_LABEL_KEYS } from './booking-links';

type Props = {
    links: PublicLink[];
};

export function BookingSocialLinks({ links }: Props) {
    const { t } = useTranslation('public');

    return (
        <ul className="flex flex-wrap gap-1">
            {links.map((link) => {
                const Icon = PLATFORM_ICONS[link.platform];

                return (
                    <li key={link.platform}>
                        <a
                            href={link.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={t(PLATFORM_LABEL_KEYS[link.platform])}
                            className="grid size-11 place-content-center rounded-full text-muted-foreground transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <Icon aria-hidden="true" className="size-5" />
                        </a>
                    </li>
                );
            })}
        </ul>
    );
}
