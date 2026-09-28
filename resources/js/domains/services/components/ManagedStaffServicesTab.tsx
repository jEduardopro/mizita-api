import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useStaffServices } from '../queries';
import type { Service } from '../types';
import { AddStaffServiceField } from './AddStaffServiceField';
import { StaffServicesPanel } from './StaffServicesPanel';
import { useStaffServiceAssignments } from './use-staff-service-assignments';

type Props = {
    staffMemberId: string;
    renderLinkAction: (service: Service) => ReactNode;
    onAssignmentsChange?: () => void;
};

export function ManagedStaffServicesTab({ staffMemberId, renderLinkAction, onAssignmentsChange }: Props) {
    const { t } = useTranslation('admin');
    const services = useStaffServices(staffMemberId);
    const assignments = useStaffServiceAssignments({
        staffMemberId,
        assigned: services.data,
        onChange: onAssignmentsChange,
    });

    return (
        <StaffServicesPanel
            services={services.data}
            loadFailed={services.isError}
            onRetry={() => void services.refetch()}
            emptyHint={t('staffServices.emptyManaged')}
            renderAction={(service) => (
                <div className="flex shrink-0 items-center gap-1">
                    {renderLinkAction(service)}

                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => assignments.unassign(service.id)}
                        disabled={assignments.pendingServiceId !== null}
                        aria-label={t('staffServices.unassign', { service: service.name })}
                        className="size-11 shrink-0 p-0 text-muted-foreground hover:text-foreground md:size-9"
                    >
                        <X aria-hidden="true" />
                    </Button>
                </div>
            )}
            addField={
                <AddStaffServiceField
                    options={assignments.options}
                    optionsStatus={assignments.optionsStatus}
                    onRetryOptions={assignments.retryOptions}
                    onSelect={assignments.assign}
                    disabled={assignments.pendingServiceId !== null}
                />
            }
        />
    );
}
