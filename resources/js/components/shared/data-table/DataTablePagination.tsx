import { cn } from 'cn';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { OnChangeFn, PaginationState } from '@tanstack/react-table';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { pageRange } from './page-range';
import { PAGE_SIZES } from './types';

type Props = {
    pagination: PaginationState;
    onPaginationChange: OnChangeFn<PaginationState>;
    pageCount: number;
    totalRows: number;
};

export function DataTablePagination({
    pagination,
    onPaginationChange,
    pageCount,
    totalRows,
}: Props) {
    const { t } = useTranslation('common');
    const pageSizeId = useId();
    const pageSizeLabelId = `${pageSizeId}-label`;

    const currentPage = pagination.pageIndex + 1;
    const firstRow = pagination.pageIndex * pagination.pageSize + 1;
    const lastRow = Math.min(firstRow + pagination.pageSize - 1, totalRows);

    function goToPage(page: number) {
        onPaginationChange({ pageIndex: page - 1, pageSize: pagination.pageSize });
    }

    return (
        <nav
            aria-label={t('table.pagination')}
            className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <Label id={pageSizeLabelId} htmlFor={pageSizeId} className="text-xs text-muted-foreground">
                    {t('table.rowsPerPage')}
                </Label>

                <Select
                    value={String(pagination.pageSize)}
                    onValueChange={(value) =>
                        onPaginationChange({ pageIndex: 0, pageSize: Number(value) })
                    }
                >
                    <SelectTrigger
                        id={pageSizeId}
                        aria-labelledby={`${pageSizeLabelId} ${pageSizeId}`}
                        className="h-11 w-20 text-base md:h-9 md:text-sm"
                    >
                        <SelectValue />
                    </SelectTrigger>

                    <SelectContent>
                        {PAGE_SIZES.map((size) => (
                            <SelectItem key={size} value={String(size)}>
                                {size}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <p aria-live="polite" className="text-xs text-muted-foreground">
                    {t('table.summary', { from: firstRow, to: lastRow, total: totalRows })}
                </p>
            </div>

            <div className="flex items-center justify-between gap-1 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={t('table.previous')}
                    disabled={currentPage <= 1}
                    onClick={() => goToPage(currentPage - 1)}
                    className="size-11 md:size-9"
                >
                    <ChevronLeft aria-hidden="true" />
                </Button>

                <p className="text-sm text-muted-foreground sm:hidden">
                    {t('table.page', { page: currentPage, pages: pageCount })}
                </p>

                <ul className="hidden items-center gap-1 sm:flex">
                    {pageRange(currentPage, pageCount).map((slot, index) =>
                        slot === 'gap' ? (
                            <li
                                key={`gap-${index}`}
                                aria-hidden="true"
                                className="px-1 text-sm text-muted-foreground"
                            >
                                …
                            </li>
                        ) : (
                            <li key={slot}>
                                <Button
                                    type="button"
                                    variant={slot === currentPage ? 'secondary' : 'ghost'}
                                    size="icon"
                                    aria-label={t('table.goToPage', { page: slot })}
                                    aria-current={slot === currentPage ? 'page' : undefined}
                                    onClick={() => goToPage(slot)}
                                    className={cn(
                                        'size-11 text-sm md:size-9',
                                        slot === currentPage ? 'font-medium' : undefined,
                                    )}
                                >
                                    {slot}
                                </Button>
                            </li>
                        ),
                    )}
                </ul>

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={t('table.next')}
                    disabled={currentPage >= pageCount}
                    onClick={() => goToPage(currentPage + 1)}
                    className="size-11 md:size-9"
                >
                    <ChevronRight aria-hidden="true" />
                </Button>
            </div>
        </nav>
    );
}
