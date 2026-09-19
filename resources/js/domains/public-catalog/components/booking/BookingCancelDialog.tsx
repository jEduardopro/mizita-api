import { CalendarX2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useServerErrors } from '@/hooks/use-server-errors';
import { isRateLimitedError } from '@/lib/http';
import { raiseSuccessToast } from '@/lib/toast';
import { useCancelPublicBooking } from '../../queries';
import type { PublicBookingCredentials } from '../../types';

type Props = {
    slug: string;
    credentials: PublicBookingCredentials;
    open: boolean;
    onOpenChange(open: boolean): void;
};

export function BookingCancelDialog({ slug, credentials, open, onOpenChange }: Props) {
    const { t } = useTranslation('public');
    const cancelBooking = useCancelPublicBooking(slug, credentials);
    const serverErrors = useServerErrors();

    async function confirm() {
        try {
            await cancelBooking.mutateAsync();
            raiseSuccessToast(t('booking.manage.cancel.success'));
            onOpenChange(false);
        } catch (error) {
            serverErrors.capture(
                error,
                isRateLimitedError(error)
                    ? t('booking.manage.errors.tooMany')
                    : t('booking.manage.errors.cancel'),
            );
        }
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogMedia>
                        <CalendarX2 aria-hidden="true" />
                    </AlertDialogMedia>

                    <AlertDialogTitle>{t('booking.manage.cancel.title')}</AlertDialogTitle>

                    <AlertDialogDescription>{t('booking.manage.cancel.body')}</AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="h-12 px-4 md:h-9">
                        {t('booking.manage.cancel.keep')}
                    </AlertDialogCancel>

                    <AlertDialogAction
                        disabled={cancelBooking.isPending}
                        onClick={(event) => {
                            event.preventDefault();
                            void confirm();
                        }}
                        className="h-12 px-4 md:h-9"
                    >
                        {t('booking.manage.cancel.confirm')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
