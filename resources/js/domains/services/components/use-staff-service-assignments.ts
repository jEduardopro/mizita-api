import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { comboboxOptionsStatus, type ComboboxOption, type ComboboxOptionsStatus } from '@/components/form/ComboboxField';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useAssignableServices, useAssignStaffToService, useUnassignStaffFromService } from '../queries';
import type { Service } from '../types';

export type StaffServiceAssignments = {
    options: ComboboxOption[];
    optionsStatus: ComboboxOptionsStatus;
    retryOptions: () => void;
    assign: (serviceId: string) => void;
    unassign: (serviceId: string) => void;
    pendingServiceId: string | null;
};

type Params = {
    staffMemberId: string;
    assigned: Service[] | undefined;
};

export function useStaffServiceAssignments({ staffMemberId, assigned }: Params): StaffServiceAssignments {
    const { t } = useTranslation('admin');
    const catalog = useAssignableServices();
    const assignMutation = useAssignStaffToService();
    const unassignMutation = useUnassignStaffFromService();
    const [pendingServiceId, setPendingServiceId] = useState<string | null>(null);

    const options = useMemo(() => {
        const assignedIds = new Set((assigned ?? []).map((service) => service.id));

        return (catalog.data?.data ?? [])
            .filter((service) => ! assignedIds.has(service.id))
            .map((service) => ({ value: service.id, label: service.name }));
    }, [catalog.data, assigned]);

    async function run(serviceId: string, change: typeof assignMutation.mutateAsync, successMessage: string) {
        setPendingServiceId(serviceId);

        try {
            await change({ serviceId, staffMemberId });
            raiseSuccessToast(successMessage);
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('staffServices.failed')));
        } finally {
            setPendingServiceId(null);
        }
    }

    return {
        options,
        optionsStatus: comboboxOptionsStatus(catalog.isPending, catalog.isError),
        retryOptions: () => void catalog.refetch(),
        assign: (serviceId) => void run(serviceId, assignMutation.mutateAsync, t('staffServices.assigned')),
        unassign: (serviceId) =>
            void run(serviceId, unassignMutation.mutateAsync, t('staffServices.unassigned')),
        pendingServiceId,
    };
}
