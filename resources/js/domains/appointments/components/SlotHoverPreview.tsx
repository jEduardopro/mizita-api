import { useEffect, useMemo, useState, type RefObject } from 'react';
import { createPortal } from 'react-dom';
import { SLOT_MINUTES } from './appointment-slots';

const DAY_MINUTES = 24 * 60;

const MILLISECONDS_PER_MINUTE = 60_000;

const FINE_POINTER_QUERY = '(hover: hover) and (pointer: fine)';

const DAY_COLUMN_SELECTOR = '.sx__time-grid-day';

const EVENT_SELECTOR = '.sx__time-grid-event';

type HoveredSlot = {
    column: HTMLElement;
    minutesFromMidnight: number;
};

type Props = {
    containerRef: RefObject<HTMLDivElement | null>;
    locale: string;
};

function slotUnderPointer(column: HTMLElement, clientY: number): number {
    const bounds = column.getBoundingClientRect();
    const minutes = ((clientY - bounds.top) / bounds.height) * DAY_MINUTES;
    const snapped = Math.floor(minutes / SLOT_MINUTES) * SLOT_MINUTES;

    return Math.min(Math.max(snapped, 0), DAY_MINUTES - SLOT_MINUTES);
}

function useHoveredSlot(containerRef: RefObject<HTMLDivElement | null>): HoveredSlot | null {
    const [hoveredSlot, setHoveredSlot] = useState<HoveredSlot | null>(null);

    useEffect(() => {
        const container = containerRef.current;

        if (container === null || ! window.matchMedia(FINE_POINTER_QUERY).matches) {
            return;
        }

        const clear = () => setHoveredSlot(null);

        const track = (event: PointerEvent) => {
            if (event.pointerType !== 'mouse' || ! (event.target instanceof Element)) {
                clear();

                return;
            }

            const column = event.target.closest<HTMLElement>(DAY_COLUMN_SELECTOR);

            if (column === null || event.target.closest(EVENT_SELECTOR) !== null) {
                clear();

                return;
            }

            const minutesFromMidnight = slotUnderPointer(column, event.clientY);

            setHoveredSlot((current) =>
                current !== null
                && current.column === column
                && current.minutesFromMidnight === minutesFromMidnight
                    ? current
                    : { column, minutesFromMidnight },
            );
        };

        container.addEventListener('pointermove', track);
        container.addEventListener('pointerleave', clear);
        container.addEventListener('pointerdown', clear);

        return () => {
            container.removeEventListener('pointermove', track);
            container.removeEventListener('pointerleave', clear);
            container.removeEventListener('pointerdown', clear);
        };
    }, [containerRef]);

    return hoveredSlot;
}

export function SlotHoverPreview({ containerRef, locale }: Props) {
    const hoveredSlot = useHoveredSlot(containerRef);

    const timeOfDayFormat = useMemo(
        () => new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: '2-digit', timeZone: 'UTC' }),
        [locale],
    );

    if (hoveredSlot === null) {
        return null;
    }

    return createPortal(
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-x-0 z-[var(--calendar-slot-preview-z-index)] flex items-center overflow-hidden rounded-sm border border-foreground/25 bg-background px-1.5 text-xs leading-none font-medium tabular-nums text-muted-foreground"
            style={{
                top: `${(hoveredSlot.minutesFromMidnight / DAY_MINUTES) * 100}%`,
                height: `${(SLOT_MINUTES / DAY_MINUTES) * 100}%`,
            }}
        >
            {timeOfDayFormat.format(hoveredSlot.minutesFromMidnight * MILLISECONDS_PER_MINUTE)}
        </div>,
        hoveredSlot.column,
    );
}
