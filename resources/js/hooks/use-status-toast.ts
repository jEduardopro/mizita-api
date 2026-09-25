import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { raiseSuccessToast } from '@/lib/toast';

export function useStatusToast(status: string, message: string): void {
    const { status: pageStatus } = usePage().props;
    const announced = useRef(false);

    useEffect(() => {
        if (pageStatus !== status) {
            announced.current = false;

            return;
        }

        if (announced.current) {
            return;
        }

        announced.current = true;
        raiseSuccessToast(message);
    }, [pageStatus, status, message]);
}
