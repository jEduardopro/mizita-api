import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useStaffServices } from '../queries';
import type { Service } from '../types';
import { StaffServicesPanel } from './StaffServicesPanel';

type Props = {
    staffMemberId: string;
    renderLinkAction: (service: Service) => ReactNode;
};

export function StaffServicesTab({ staffMemberId, renderLinkAction }: Props) {
    const { t } = useTranslation('admin');
    const services = useStaffServices(staffMemberId);

    return (
        <StaffServicesPanel
            services={services.data}
            loadFailed={services.isError}
            onRetry={() => void services.refetch()}
            emptyHint={t('staffServices.emptyReadOnly')}
            renderAction={renderLinkAction}
        />
    );
}
