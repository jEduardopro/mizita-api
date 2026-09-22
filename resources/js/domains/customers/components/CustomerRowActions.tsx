import { Link, usePage } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuthorization } from '@/hooks/use-authorization';
import { withReturnTo } from '@/lib/return-to';
import type { Customer } from '../types';
import { customerEditUrl } from './customer-urls';
import { DeleteCustomerDialog } from './DeleteCustomerDialog';

type Props = {
    customer: Customer;
};

export function CustomerRowActions({ customer }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const { can } = useAuthorization();
    const { url } = usePage();

    const canEdit = can('edit_customer');
    const canDelete = can('delete_customer');

    if (! canEdit && ! canDelete) {
        return null;
    }

    return (
        <div className="flex shrink-0 items-center gap-1">
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
                    {canEdit ? (
                        <DropdownMenuItem asChild className="min-h-11 md:min-h-8">
                            <Link href={withReturnTo(customerEditUrl(customer.id), url)}>
                                <Pencil aria-hidden="true" />
                                {tCommon('actions.edit')}
                            </Link>
                        </DropdownMenuItem>
                    ) : null}

                    {canEdit && canDelete ? <DropdownMenuSeparator /> : null}

                    {canDelete ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setConfirmingDelete(true)}
                            className="min-h-11 md:min-h-8"
                        >
                            <Trash2 aria-hidden="true" />
                            {tCommon('actions.delete')}
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            {canDelete ? (
                <DeleteCustomerDialog
                    customer={customer}
                    open={confirmingDelete}
                    onOpenChange={setConfirmingDelete}
                />
            ) : null}
        </div>
    );
}
