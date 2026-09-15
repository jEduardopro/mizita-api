import { useEffect, useRef, type RefObject } from 'react';

const DEFAULT_ROOT_MARGIN = '400px';

type Params = {
    enabled: boolean;
    onIntersect: () => void;
    rootMargin?: string;
};

export function useIntersection({
    enabled,
    onIntersect,
    rootMargin = DEFAULT_ROOT_MARGIN,
}: Params): RefObject<HTMLDivElement | null> {
    const sentinelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const sentinel = sentinelRef.current;

        if (! enabled || sentinel === null) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    onIntersect();
                }
            },
            { rootMargin },
        );

        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [enabled, onIntersect, rootMargin]);

    return sentinelRef;
}
