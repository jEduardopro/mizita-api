import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableRow } from '@/components/ui/table';

const PLACEHOLDER_ROWS = [0, 1, 2, 3, 4];

type Props = {
    columns: number;
};

export function DataTableSkeleton({ columns }: Props) {
    const { t } = useTranslation('common');
    const cells = Array.from({ length: columns }, (_, index) => index);

    return (
        <div role="status" aria-busy="true" aria-label={t('table.loading')}>
            <div className="grid gap-3 md:hidden">
                {PLACEHOLDER_ROWS.map((row) => (
                    <Skeleton key={row} className="h-[4.5rem] rounded-xl" />
                ))}
            </div>

            <div className="hidden overflow-hidden rounded-xl border border-border md:block">
                <Table>
                    <TableBody>
                        {PLACEHOLDER_ROWS.map((row) => (
                            <TableRow key={row}>
                                {cells.map((cell) => (
                                    <TableCell key={cell} className="p-3">
                                        <Skeleton className="h-4 w-full" />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
