import { router, usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

type QueryPatch = Record<string, string | null>;

type UrlQueryState = {
    read(key: string): string | null;
    write(patch: QueryPatch): void;
};

function pathOf(url: string): string {
    return url.split('?')[0];
}

function searchOf(url: string): string {
    return url.split('?')[1] ?? '';
}

export function useUrlQueryState(): UrlQueryState {
    const { url } = usePage();

    const parameters = useMemo(() => new URLSearchParams(searchOf(url)), [url]);

    const read = useCallback((key: string) => parameters.get(key), [parameters]);

    const write = useCallback(
        (patch: QueryPatch) => {
            const next = new URLSearchParams(parameters);

            for (const [key, value] of Object.entries(patch)) {
                if (value === null) {
                    next.delete(key);

                    continue;
                }

                next.set(key, value);
            }

            const query = next.toString();

            router.replace({
                url: query === '' ? pathOf(url) : `${pathOf(url)}?${query}`,
                preserveState: true,
                preserveScroll: true,
            });
        },
        [parameters, url],
    );

    return useMemo(() => ({ read, write }), [read, write]);
}
