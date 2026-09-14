import { useTranslation } from 'react-i18next';
import { Toaster } from '@/components/ui/sonner';

/**
 * A wrapper rather than an edit to `components/ui/sonner.tsx`, which is generated
 * and stays regenerable.
 *
 * `top-center` because the phone decides it: a notification anchored to the
 * bottom of a small screen lands on the submit button and on the keyboard.
 *
 * The two labels are the region's only copy. What a toast says is not: a server
 * message arrives already written in the caller's language and is shown verbatim.
 */
export function AppToaster() {
    const { t } = useTranslation('common');

    return (
        <Toaster
            position="top-center"
            closeButton
            containerAriaLabel={t('notifications.region')}
            // Spread over the generated options rather than merged with them, so
            // the class the registry attaches to every toast is carried forward
            // here. Dropping it would leave nothing for a future shadcn release
            // to style against.
            toastOptions={{
                closeButtonAriaLabel: t('notifications.dismiss'),
                classNames: { toast: 'cn-toast' },
            }}
        />
    );
}
