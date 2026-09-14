import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { useErrorToast } from '@/hooks/use-error-toast';

export function useFlashToast(): void {
    const { flash } = usePage().props;
    const errorToast = useErrorToast();

    useEffect(() => {
        if (flash.error === null) {
            return;
        }

        errorToast.show(flash.error);
    }, [flash.error, errorToast]);
}
