import { useStateName } from '@/domains/addresses/queries';
import type { CustomerAddress } from '../types';
import { addressLines, addressStateName } from './customer-format';

type Props = {
    address: CustomerAddress;
};

export function CustomerAddressLines({ address }: Props) {
    const catalogStateName = useStateName(address.country_code, address.state_id);
    const lines = addressLines(address, addressStateName(address, catalogStateName));

    return lines.map((line) => (
        <span key={line} className="block">
            {line}
        </span>
    ));
}
