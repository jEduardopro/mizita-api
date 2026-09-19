import 'temporal-polyfill/global';
import { useEffect, useRef, type RefObject } from 'react';

const SCROLL_CONTAINER_SELECTOR = '.sx__view-container';

const DAY_COLUMN_SELECTOR = '.sx__time-grid-day';

const STICKY_HEADER_SELECTOR = '.sx__week-header';

const MINUTES_PER_HOUR = 60;

const MINUTES_PER_DAY = 24 * MINUTES_PER_HOUR;

const GRID_VISIBLE_ABOVE_MARKER = 20;

const MAX_LAYOUT_FRAMES = 30;

type TimeGrid = {
    container: HTMLElement;
    column: HTMLElement;
};

function measurableTimeGrid(wrapper: HTMLElement): TimeGrid | null {
    const container = wrapper.querySelector<HTMLElement>(SCROLL_CONTAINER_SELECTOR);
    const column = wrapper.querySelector<HTMLElement>(DAY_COLUMN_SELECTOR);

    if (container === null || column === null || column.getBoundingClientRect().height === 0) {
        return null;
    }

    return { container, column };
}

function scrollTopForMinuteOfDay({ container, column }: TimeGrid, minuteOfDay: number): number {
    const columnBounds = column.getBoundingClientRect();
    const columnTop = columnBounds.top - container.getBoundingClientRect().top + container.scrollTop;
    const markerTop = columnTop + (minuteOfDay / MINUTES_PER_DAY) * columnBounds.height;
    const header = container.querySelector(STICKY_HEADER_SELECTOR);
    const headerHeight = header === null ? 0 : header.getBoundingClientRect().height;

    return Math.max(markerTop - headerHeight - GRID_VISIBLE_ABOVE_MARKER, 0);
}

export function useCalendarInitialScroll(wrapperRef: RefObject<HTMLDivElement | null>, timezone: string) {
    const hasScrolled = useRef(false);

    useEffect(() => {
        const wrapper = wrapperRef.current;

        if (wrapper === null || hasScrolled.current) {
            return;
        }

        let remainingFrames = MAX_LAYOUT_FRAMES;
        let frame = 0;

        const scrollWhenMeasurable = () => {
            const timeGrid = measurableTimeGrid(wrapper);

            if (timeGrid === null) {
                remainingFrames -= 1;

                if (remainingFrames > 0) {
                    frame = requestAnimationFrame(scrollWhenMeasurable);
                }

                return;
            }

            hasScrolled.current = true;

            const now = Temporal.Now.plainTimeISO(timezone);

            timeGrid.container.scrollTo({
                top: scrollTopForMinuteOfDay(timeGrid, now.hour * MINUTES_PER_HOUR + now.minute),
                behavior: 'instant',
            });
        };

        frame = requestAnimationFrame(scrollWhenMeasurable);

        return () => cancelAnimationFrame(frame);
    }, [wrapperRef, timezone]);
}
