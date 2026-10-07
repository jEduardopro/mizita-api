import type { Breadcrumb } from '@/components/admin/shell/AdminBreadcrumbs';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { RETURN_PARAMETER, safeReturnTo } from '@/lib/return-to';

export type BreadcrumbOrigin = {
    matches(path: string): boolean;
    trail(path: string): Breadcrumb[];
};

const NO_ORIGIN = '';

export function useOriginBreadcrumbs(
    defaultTrail: Breadcrumb[],
    origins: readonly BreadcrumbOrigin[],
): Breadcrumb[] {
    const originPath = safeReturnTo(useUrlQueryState().read(RETURN_PARAMETER), NO_ORIGIN);

    if (originPath === NO_ORIGIN) {
        return defaultTrail;
    }

    const origin = origins.find((candidate) => candidate.matches(originPath));

    return origin === undefined ? defaultTrail : origin.trail(originPath);
}
