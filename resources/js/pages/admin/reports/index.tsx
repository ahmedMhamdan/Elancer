// Adapted from TailAdmin src/components/tables/BasicTables/BasicTableOne.tsx through the
// existing table and pagination adaptations. MIT: THIRD_PARTY_NOTICES.md.
import { Head, Link } from '@inertiajs/react';
import Pagination, {
    type PaginationData,
} from '@/components/tailadmin/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
    TableScroll,
} from '@/components/tailadmin/table';
import { useReportLabels } from '@/hooks/use-report-labels';
import { useTranslation } from '@/hooks/use-translation';

type Row = {
    id: number;
    target_type: string;
    title: string;
    reason: string;
    status: string;
    outcome: string | null;
    created_at: string;
    reporter: string | null;
    subject: string | null;
    handler: string | null;
};

export default function Reports({
    reports,
    status,
}: {
    reports: PaginationData & { data: Row[] };
    status: 'open' | 'resolved';
}) {
    const { t } = useTranslation();
    const { targets, reasons, statuses, time } = useReportLabels();
    const head =
        'text-muted-foreground px-4 py-3 text-start text-sm font-medium';
    const linkClass =
        'inline-flex min-h-11 items-center rounded-lg px-4 py-2 font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring';
    const tabs = {
        open: t('Open reports'),
        resolved: t('Resolved reports'),
    };
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('Reports')} />
            <div className="workspace-page-heading">
                <h1>{t('Reports')}</h1>
                <p>
                    {t(
                        'Reports from members. Open reports are listed oldest first.',
                    )}
                </p>
            </div>
            <nav aria-label={t('Reports')} className="flex gap-2">
                {(['open', 'resolved'] as const).map((value) => (
                    <Link
                        key={value}
                        href={`/admin/reports?status=${value}`}
                        className={`${linkClass} ${status === value ? 'bg-muted' : ''}`}
                        aria-current={status === value ? 'page' : undefined}
                    >
                        {tabs[value]}
                    </Link>
                ))}
            </nav>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                <TableScroll label={t('Reports')}>
                    <Table className="w-full">
                        <TableHeader className="border-border border-b">
                            <TableRow>
                                <TableCell isHeader className={head}>
                                    {t('Report')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Reason')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Reported by')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Reported member')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Status')}
                                </TableCell>
                                <TableCell
                                    isHeader
                                    className="text-muted-foreground w-px px-4 py-3 text-end text-sm font-medium whitespace-nowrap"
                                >
                                    {t('Actions')}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-border divide-y">
                            {reports.data.map((report) => (
                                <TableRow key={report.id}>
                                    <TableCell className="px-4 py-4">
                                        <div className="max-w-xs min-w-48">
                                            <p className="text-muted-foreground text-sm">
                                                #{report.id} ·{' '}
                                                {targets[report.target_type]}
                                            </p>
                                            <p className="font-medium wrap-anywhere">
                                                <bdi>{report.title}</bdi>
                                            </p>
                                            <time
                                                dateTime={report.created_at}
                                                className="text-muted-foreground text-sm"
                                            >
                                                {time(report.created_at)}
                                            </time>
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <span className="block min-w-32">
                                            {reasons[report.reason]}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <span className="block min-w-28 wrap-anywhere">
                                            <bdi>
                                                {report.reporter ??
                                                    t('Closed account')}
                                            </bdi>
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <span className="block min-w-28 wrap-anywhere">
                                            <bdi>
                                                {report.subject ??
                                                    t('Closed account')}
                                            </bdi>
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-4">
                                        <p className="font-medium whitespace-nowrap">
                                            {statuses[report.status]}
                                        </p>
                                        {report.handler && (
                                            <p className="text-muted-foreground text-sm wrap-anywhere">
                                                <bdi>{report.handler}</bdi>
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="w-px px-4 py-4 text-end">
                                        <Link
                                            href={`/admin/reports/${report.id}`}
                                            className={`${linkClass} whitespace-nowrap`}
                                            aria-label={`${t('Open report')}: #${report.id}`}
                                        >
                                            {t('Open report')}
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!reports.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground px-5 py-12 text-center"
                                    >
                                        {status === 'open'
                                            ? t('No open reports.')
                                            : t('No resolved reports yet.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableScroll>
            </div>
            <Pagination data={reports} />
        </div>
    );
}
