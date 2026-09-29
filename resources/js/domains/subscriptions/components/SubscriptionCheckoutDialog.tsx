import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { CheckoutPaymentPanel } from './CheckoutPaymentPanel';
import { CheckoutPlanDetails } from './CheckoutPlanDetails';
import { useIsPayingCheckout, type CheckoutStart } from '../queries';
import type { Subscription } from '../types';

const FULL_SCREEN_CLASS_NAME =
    'inset-0 top-0 left-0 flex h-svh w-full max-w-none translate-x-0 translate-y-0 flex-col gap-0 rounded-none bg-background p-0 ring-0 sm:max-w-none';

const PAYMENT_HEADING_ID = 'checkout-payment-heading';

type Props = {
    open: boolean;
    onClose: () => void;
    checkout: CheckoutStart | undefined;
    startError: Error | null;
    onRetry: () => void;
    onSubscribed: (subscription: Subscription) => void;
};

export function SubscriptionCheckoutDialog({
    open,
    onClose,
    checkout,
    startError,
    onRetry,
    onSubscribed,
}: Props) {
    const { t } = useTranslation('admin');
    const isPaying = useIsPayingCheckout();

    function handleOpenChange(next: boolean) {
        if (next || isPaying) {
            return;
        }

        onClose();
    }

    return (
        // Non-modal on purpose: Stripe mounts 3D Secure and wallet challenges in iframes
        // outside this dialog, and a modal dialog blocks pointer events on everything outside it.
        <Dialog open={open} onOpenChange={handleOpenChange} modal={false}>
            <DialogContent
                showCloseButton={false}
                onInteractOutside={(event) => event.preventDefault()}
                className={FULL_SCREEN_CLASS_NAME}
            >
                <div className="flex shrink-0 items-center justify-between gap-4 border-b border-border px-5 py-1.5 sm:px-8">
                    <DialogTitle className="text-base">{t('plan.checkout.title')}</DialogTitle>

                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-lg"
                            disabled={isPaying}
                            aria-label={t('plan.checkout.close')}
                            className="size-11 -mr-2.5"
                        >
                            <X aria-hidden="true" className="size-5" />
                        </Button>
                    </DialogClose>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <div className="mx-auto grid w-full max-w-5xl gap-8 px-5 pt-6 pb-[max(2rem,env(safe-area-inset-bottom))] sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-16 lg:py-14">
                        <div className="lg:sticky lg:top-14 lg:col-start-2 lg:row-start-1 lg:self-start">
                            <CheckoutPlanDetails plan={checkout?.plan} />
                        </div>

                        <section
                            aria-labelledby={PAYMENT_HEADING_ID}
                            className="grid min-w-0 content-start gap-6 lg:col-start-1 lg:row-start-1 lg:max-w-xl"
                        >
                            <header className="grid gap-2">
                                <h2
                                    id={PAYMENT_HEADING_ID}
                                    className="font-heading text-[clamp(1.5rem,3.5vw,2rem)] leading-[1.1] font-medium tracking-[-0.03em]"
                                >
                                    {t('plan.checkout.heading')}
                                </h2>

                                <DialogDescription className="text-pretty">
                                    {t('plan.checkout.description')}
                                </DialogDescription>
                            </header>

                            <CheckoutPaymentPanel
                                session={checkout?.session}
                                startError={startError}
                                onRetry={onRetry}
                                onSubscribed={onSubscribed}
                                onCancel={onClose}
                            />
                        </section>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
