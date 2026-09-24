import { useTranslation } from 'react-i18next';
import { useStaffServices } from '../queries';
import { StaffServicesPanel } from './StaffServicesPanel';

type Props = {
    staffMemberId: string;
};

export function StaffServicesTab({ staffMemberId }: Props) {
    const { t } = useTranslation('admin');
    const services = useStaffServices(staffMemberId);

    return (
        <StaffServicesPanel
            services={services.data}
            loadFailed={services.isError}
            onRetry={() => void services.refetch()}
            emptyHint={t('staffServices.emptyReadOnly')}
        />
    );
}
