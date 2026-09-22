import { Link, usePage } from '@inertiajs/react';
import { CalendarPlus, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuthorization } from '@/hooks/use-authorization';
import { withReturnTo } from '@/lib/return-to';
import type { Customer } from '../types';
import { customerEditUrl } from './customer-urls';
import { DeleteCustomerDialog } from './DeleteCustomerDialog';

type Props = {
    customer: Customer;
    onBook: () => void;
    onDeleted: () => void;
};

export function CustomerShowActions({ customer, onBook, onDeleted }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const { can } = useAuthorization();
    const { url } = usePage();

    const canBook = can('create_appointment');
    const canEdit = can('edit_customer');
    const canDelete = can('delete_customer');

    if (! canBook && ! canEdit && ! canDelete) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {canBook ? (
                <Button
                    type="button"
                    variant="brand"
                    onClick={onBook}
                    className="h-11 w-full px-4 sm:w-auto md:h-9"
                >
                    <CalendarPlus aria-hidden="true" />
                    {t('customers.show.actions.book')}
                </Button>
            ) : null}

            {canEdit ? (
                <Button asChild variant="outline" size="icon" className="size-11 md:size-9">
                    <Link
                        href={withReturnTo(customerEditUrl(customer.id), url)}
                        aria-label={t('customers.actions.edit', { name: customer.name })}
                    >
                        <Pencil aria-hidden="true" />
                    </Link>
                </Button>
            ) : null}

            {canDelete ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t('customers.actions.more', { name: customer.name })}
                            className="size-11 md:size-9"
                        >
                            <MoreHorizontal aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setConfirmingDelete(true)}
                            className="min-h-11 md:min-h-8"
                        >
                            <Trash2 aria-hidden="true" />
                            {tCommon('actions.delete')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : null}

            {canDelete ? (
                <DeleteCustomerDialog
                    customer={customer}
                    open={confirmingDelete}
                    onOpenChange={setConfirmingDelete}
                    onDeleted={onDeleted}
                />
            ) : null}
        </div>
    );
}
