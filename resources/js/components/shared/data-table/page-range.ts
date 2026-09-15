export type PageSlot = number | 'gap';

const SIBLING_COUNT = 1;

const SLOTS_WITHOUT_GAPS = 7;

function sequence(from: number, to: number): number[] {
    return Array.from({ length: to - from + 1 }, (_, offset) => from + offset);
}

export function pageRange(currentPage: number, lastPage: number): PageSlot[] {
    if (lastPage <= SLOTS_WITHOUT_GAPS) {
        return sequence(1, Math.max(lastPage, 1));
    }

    const windowStart = Math.max(currentPage - SIBLING_COUNT, 2);
    const windowEnd = Math.min(currentPage + SIBLING_COUNT, lastPage - 1);

    const head: PageSlot[] = windowStart > 2 ? [1, 'gap'] : [1];
    const tail: PageSlot[] = windowEnd < lastPage - 1 ? ['gap', lastPage] : [lastPage];

    return [...head, ...sequence(windowStart, windowEnd), ...tail];
}
