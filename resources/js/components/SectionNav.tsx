import { cn } from 'cn';
import type { MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useActiveSection } from '@/hooks/use-active-section';

export type Section = {
    /** The `id` of the element on the page this entry scrolls to. */
    id: string;
    label: string;
};

type Props = {
    sections: Section[];
    className?: string;
};

/**
 * The in-page menu in the public header: one link per long section of the page
 * the visitor is already on.
 *
 * Each link is the full height of the header so the current marker can sit on
 * the header's own bottom rule, which reads as a tab strip rather than as an
 * underline floating in the middle of the bar. Below `md` the links are hidden
 * altogether: the account buttons are the only thing a narrow header has room
 * for, and every section is a short scroll away anyway.
 */
export function SectionNav({ sections, className }: Props) {
    const { t } = useTranslation('common');
    const activeId = useActiveSection(sections.map((section) => section.id));

    function handleClick(event: MouseEvent<HTMLAnchorElement>, id: string): void {
        const section = document.getElementById(id);

        if (section === null) {
            return;
        }

        event.preventDefault();

        // Smooth by default, instant for anyone who has asked the system for
        // less motion. Read per click rather than once, so a preference changed
        // mid-session is honoured without a reload.
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        section.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });

        // Reading order follows the click: without this the next Tab would carry
        // on through the header instead of into the section that just arrived.
        section.focus({ preventScroll: true });

        // Leaves the anchor in the address bar so the position is shareable,
        // without the jump a real hash change would cause.
        window.history.replaceState(null, '', `#${id}`);
    }

    return (
        <nav aria-label={t('nav.sections')} className={cn('hidden items-center md:flex', className)}>
            {sections.map((section) => {
                const isActive = section.id === activeId;

                return (
                    <a
                        key={section.id}
                        href={`#${section.id}`}
                        aria-current={isActive ? 'true' : undefined}
                        onClick={(event) => handleClick(event, section.id)}
                        className={cn(
                            'relative flex h-14 items-center rounded-md px-2.5 text-[0.6875rem] font-medium tracking-[0.14em] uppercase transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                            isActive ? 'text-foreground' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {section.label}

                        {/*
                         * Sits on top of the header's bottom border, so the
                         * current section is marked by the same hairline the
                         * rest of the page is built from — in brand colour, so
                         * the marker reads as a position rather than as a rule
                         * that happens to be darker.
                         */}
                        <span
                            aria-hidden="true"
                            className={cn(
                                'absolute inset-x-2.5 -bottom-px h-px transition-colors',
                                isActive ? 'bg-primary' : 'bg-transparent',
                            )}
                        />
                    </a>
                );
            })}
        </nav>
    );
}
