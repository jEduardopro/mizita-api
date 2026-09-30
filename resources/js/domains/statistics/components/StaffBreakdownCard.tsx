import { UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatFixedMoneyFromCents } from '@/lib/money';
import type { StaffMemberCollection } from '../types';
import { BreakdownEmpty } from './BreakdownEmpty';
import { ShareRow } from './ShareRow';
import { formatSharePercent } from './statistics-format';

type Props = {
    staffMembers: StaffMemberCollection[];
};

export function StaffBreakdownCard({ staffMembers }: Props) {
    const { t, i18n } = useTranslation('admin');
    const locale = i18n.language;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('statistics.byStaff.title')}</CardTitle>
            </CardHeader>

            <CardContent>
                {staffMembers.length === 0 ? (
                    <BreakdownEmpty icon={UserRound} message={t('statistics.byStaff.empty')} />
                ) : (
                    <ul className="grid gap-2">
                        {staffMembers.map((staffMember) => (
                            <ShareRow
                                key={staffMember.id}
                                label={staffMember.name}
                                detail={staffMember.email}
                                amount={formatFixedMoneyFromCents(staffMember.collected_cents)}
                                caption={t('statistics.byStaff.summary', {
                                    share: formatSharePercent(staffMember.share_percent, locale),
                                    count: staffMember.attended_appointments,
                                })}
                                sharePercent={staffMember.share_percent}
                            />
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
