// Table composition follows local TailAdmin src/components/ecommerce/RecentOrders.tsx
// (MIT); see finance/index.tsx and THIRD_PARTY_NOTICES.md.
import { Link } from '@inertiajs/react';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
    TableScroll,
} from '@/components/tailadmin/table';
import { useTranslation } from '@/hooks/use-translation';
import { Money } from '@/pages/discovery/shared';
import {
    OfferTime,
    PaymentStatus,
    ProviderMark,
    useProviderLabel,
    type Payment,
} from '@/pages/offers/shared';

export type Attempt = Payment & {
    contract_id: number;
    project_title: string | null;
};

export const head =
    'text-muted-foreground px-4 py-3 text-start text-sm font-medium whitespace-nowrap';
export const cell = 'px-4 py-4 text-start text-sm';

export function PaymentHistory({ attempts }: { attempts: Attempt[] }) {
    const { t } = useTranslation();
    const providerLabel = useProviderLabel();
    if (!attempts.length)
        return (
            <p className="market-muted px-4 pb-5">
                {t(
                    'No payment attempts yet. They appear here when a client starts funding a contract.',
                )}
            </p>
        );
    return (
        <TableScroll label={t('Payment history')}>
            <Table className="w-full">
                <TableHeader className="border-border border-y">
                    <TableRow>
                        <TableCell isHeader className={head}>
                            {t('Reference')}
                        </TableCell>
                        <TableCell isHeader className={head}>
                            {t('Project')}
                        </TableCell>
                        <TableCell isHeader className={head}>
                            {t('Payment provider')}
                        </TableCell>
                        <TableCell isHeader className={head}>
                            {t('Amount')}
                        </TableCell>
                        <TableCell isHeader className={head}>
                            {t('Status')}
                        </TableCell>
                        <TableCell isHeader className={head}>
                            {t('Started')}
                        </TableCell>
                    </TableRow>
                </TableHeader>
                <TableBody className="divide-border divide-y">
                    {attempts.map((attempt) => (
                        <TableRow key={attempt.id}>
                            <TableCell className={cell}>
                                <bdi className="font-mono text-xs">
                                    {attempt.reference}
                                </bdi>
                            </TableCell>
                            <TableCell className={cell}>
                                <Link
                                    href={`/contracts/${attempt.contract_id}`}
                                    dir="auto"
                                    className="font-medium wrap-anywhere underline-offset-4 hover:underline"
                                >
                                    {attempt.project_title}
                                </Link>
                            </TableCell>
                            <TableCell className={cell}>
                                <span className="flex items-center gap-2 whitespace-nowrap">
                                    <ProviderMark provider={attempt.provider} />
                                    {providerLabel(attempt.provider)}
                                </span>
                            </TableCell>
                            <TableCell className={`${cell} whitespace-nowrap`}>
                                <Money
                                    min={attempt.amount}
                                    max={attempt.amount}
                                />
                            </TableCell>
                            <TableCell className={cell}>
                                <PaymentStatus status={attempt.status} />
                            </TableCell>
                            <TableCell className={`${cell} whitespace-nowrap`}>
                                <OfferTime value={attempt.created_at} />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </TableScroll>
    );
}
