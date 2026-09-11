import { useEffect, useState } from 'react';

/**
 * The id of the section the reader is currently looking at, for the in-page menu
 * in the public header.
 *
 * The observing band is a narrow strip near the top of the viewport rather than
 * the whole screen: a section becomes current the moment its heading clears the
 * sticky header, and hands over as soon as the next one arrives. Watching the
 * full viewport instead would keep two long sections current at once and make
 * the highlight lag behind what is actually being read.
 */
const OBSERVED_BAND = '-20% 0px -70% 0px';

export function useActiveSection(ids: string[]): string | undefined {
    // Callers build their list while rendering, because the labels are
    // translated, so the array is a new one on every render. Keying the effect
    // on the joined ids instead keeps a single observer alive for as long as the
    // sections themselves do not change.
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

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        inBand.add(entry.target.id);
                    } else {
                        inBand.delete(entry.target.id);
                    }
                }

                // Resolved in document order, so while two sections share the
                // band the one higher up the page wins — the same one whose
                // heading the reader has just passed.
                setActiveId(sectionIds.find((id) => inBand.has(id)));
            },
            { rootMargin: OBSERVED_BAND },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, [key]);

    return activeId;
}
