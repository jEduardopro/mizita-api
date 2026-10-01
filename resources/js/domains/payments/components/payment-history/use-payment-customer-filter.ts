import { PAYMENT_REPORT_CUSTOMER_FILTER_MAX_SIZE } from '../../types';
import { isUuid } from './payment-filter-values';
import { CUSTOMER_IDS_PARAMETER } from './payment-history-parameters';
import { useUrlListFilter, type UrlListFilter } from './use-url-list-filter';

export function usePaymentCustomerFilter(): UrlListFilter<string> {
    return useUrlListFilter({
        parameter: CUSTOMER_IDS_PARAMETER,
        accepts: isUuid,
        maximumSize: PAYMENT_REPORT_CUSTOMER_FILTER_MAX_SIZE,
    });
}
