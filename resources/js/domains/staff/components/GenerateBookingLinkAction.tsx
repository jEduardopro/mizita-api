import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useGenerateStaffBookingLink } from '../queries';
import { INLINE_ACTION } from './inline-action';

type Props = {
    staffMemberId: string;
};

export function GenerateBookingLinkAction({ staffMemberId }: Props) {
    const { t } = useTranslation('admin');
    const generateLink = useGenerateStaffBookingLink();

    async function generate() {
        try {
            await generateLink.mutateAsync(staffMemberId);
            raiseSuccessToast(t('profile.bookingLink.generated'));
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('profile.bookingLink.generateFailed')));
        }
    }

    return (
        <button
            type="button"
            onClick={() => void generate()}
            disabled={generateLink.isPending}
            aria-busy={generateLink.isPending}
            className={cn(INLINE_ACTION, 'disabled:text-muted-foreground')}
        >
            {generateLink.isPending ? t('profile.bookingLink.generating') : t('profile.bookingLink.generate')}
        </button>
    );
}
