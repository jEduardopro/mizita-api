import { useEffect, useState } from 'react';

const OBSERVED_BAND = '-20% 0px -70% 0px';

const BOTTOM_REACHED_THRESHOLD = 24;

function isScrolledToBottom(): boolean {
    const scrolled = window.scrollY + window.innerHeight;

    return scrolled >= document.documentElement.scrollHeight - BOTTOM_REACHED_THRESHOLD;
}

export function useActiveSection(ids: string[]): string | undefined {
    const key = ids.join(',');
    const [activeId, setActiveId] = useState<string>();

    useEffect(() => {
        const sectionIds = key.split(',').filter((id) => id !== '');
        const sections = sectionIds
            .map((id) => document.getElementById(id))
            .filter((section) => section !== null);

        if (sections.length === 0) {
            return;
        }

        const inBand = new Set<string>();
        const lastId = sectionIds.at(-1);

        function resolveActiveId(): void {
            setActiveId(isScrolledToBottom() ? lastId : sectionIds.find((id) => inBand.has(id)));
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        inBand.add(entry.target.id);
                    } else {
                        inBand.delete(entry.target.id);
                    }
                }

                resolveActiveId();
            },
            { rootMargin: OBSERVED_BAND },
        );

        sections.forEach((section) => observer.observe(section));

        window.addEventListener('scroll', resolveActiveId, { passive: true });
        window.addEventListener('resize', resolveActiveId);

        return () => {
            observer.disconnect();
            window.removeEventListener('scroll', resolveActiveId);
            window.removeEventListener('resize', resolveActiveId);
        };
    }, [key]);

    return activeId;
}
