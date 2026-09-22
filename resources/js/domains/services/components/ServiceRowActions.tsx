import { Link, router } from '@inertiajs/react';
import { CopyPlus, Link2, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
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
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useDuplicateService } from '../queries';
import type { Service } from '../types';
import { DeleteServiceDialog } from './DeleteServiceDialog';
import { serviceEditUrl } from './service-urls';

type Props = {
    service: Service;
};

export function ServiceRowActions({ service }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const duplicateService = useDuplicateService();
    const { can } = useAuthorization();

    const copy = useCopyToClipboard({
        copied: t('services.toasts.linkCopied'),
        failed: t('services.errors.copyFailed'),
    });

    const canEdit = can('edit_service');
    const canDuplicate = can('create_service');
    const canDelete = can('delete_service');
    const hasMenuItems = canEdit || canDuplicate || canDelete;

    async function duplicate() {
        try {
            const copy = await duplicateService.mutateAsync({
                id: service.id,
                payload: { name: t('services.copyName', { name: service.name }) },
            });

            raiseSuccessToast(t('services.toasts.duplicated'));
            router.visit(serviceEditUrl(copy.id));
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('services.errors.duplicateFailed')));
        }
    }

    return (
        <div className="flex shrink-0 items-center gap-1">
            <Button
                type="button"
                variant="ghost"
                onClick={() => copy(service.booking_url)}
                className="size-11 md:h-9 md:w-auto md:gap-1.5 md:px-3"
            >
                <Link2 aria-hidden="true" />
                <span className="sr-only md:not-sr-only">{t('services.actions.copyLink')}</span>
            </Button>

            {hasMenuItems ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t('services.actions.more', { name: service.name })}
                            className="size-11 md:size-9"
                        >
                            <MoreHorizontal aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" className="w-48">
                        {canEdit ? (
                            <DropdownMenuItem asChild className="min-h-11 md:min-h-8">
                                <Link href={serviceEditUrl(service.id)}>
                                    <Pencil aria-hidden="true" />
                                    {tCommon('actions.edit')}
                                </Link>
                            </DropdownMenuItem>
                        ) : null}

                        {canDuplicate ? (
                            <DropdownMenuItem
                                disabled={duplicateService.isPending}
                                onSelect={() => void duplicate()}
                                className="min-h-11 md:min-h-8"
                            >
                                <CopyPlus aria-hidden="true" />
                                {tCommon('actions.duplicate')}
                            </DropdownMenuItem>
                        ) : null}

                        {canDelete && (canEdit || canDuplicate) ? <DropdownMenuSeparator /> : null}

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
            ) : null}

            {canDelete ? (
                <DeleteServiceDialog
                    service={service}
                    open={confirmingDelete}
                    onOpenChange={setConfirmingDelete}
                />
            ) : null}
        </div>
    );
}
