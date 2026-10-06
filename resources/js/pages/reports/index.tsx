// Adapts TailAdmin BasicTableOne through the existing table and pagination
// adaptations. MIT: THIRD_PARTY_NOTICES.md.
import { Head } from '@inertiajs/react';
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

type MyReport = {
    id: number;
    target_type: string;
    title: string;
    reason: string;
    explanation: string;
    status: string;
    outcome: string | null;
    created_at: string;
    resolved_at: string | null;
};

export default function MyReports({
    reports,
}: {
    reports: PaginationData & { data: MyReport[] };
}) {
    const { t } = useTranslation();
    const { targets, reasons, statuses, outcomes, time } = useReportLabels();
    const head =
        'text-muted-foreground px-4 py-3 text-start text-sm font-medium';
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('My reports')} />
            <div className="workspace-page-heading">
                <h1>{t('My reports')}</h1>
                <p>
                    {t(
                        'Reports you sent to the Elancer team and where each one stands.',
                    )}
                </p>
            </div>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                <TableScroll label={t('My reports')}>
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
                                    {t('Status')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Sent')}
                                </TableCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-border divide-y">
                            {reports.data.map((report) => (
                                <TableRow key={report.id}>
                                    <TableCell className="px-4 py-4 align-top">
                                        <div className="max-w-md min-w-56">
                                            <p className="text-muted-foreground text-sm">
                                                {targets[report.target_type]}
                                            </p>
                                            <p className="font-medium wrap-anywhere">
                                                <bdi>{report.title}</bdi>
                                            </p>
                                            <details className="mt-2 text-sm">
                                                <summary className="focus-visible:outline-ring inline-flex min-h-11 cursor-pointer items-center underline underline-offset-4 focus-visible:outline-2">
                                                    {t('What you wrote')}
                                                </summary>
                                                <p
                                                    dir="auto"
                                                    className="mt-1 wrap-anywhere whitespace-pre-wrap"
                                                >
                                                    {report.explanation}
                                                </p>
                                            </details>
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 align-top">
                                        <span className="block min-w-32">
                                            {reasons[report.reason]}
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 align-top">
                                        <div className="max-w-sm min-w-40">
                                            <p className="font-medium whitespace-nowrap">
                                                {statuses[report.status]}
                                            </p>
                                            {report.outcome && (
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    {outcomes[report.outcome]}
                                                </p>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 align-top text-sm whitespace-nowrap">
                                        <time dateTime={report.created_at}>
                                            {time(report.created_at)}
                                        </time>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!reports.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-muted-foreground px-5 py-12 text-center"
                                    >
                                        {t(
                                            'You have not sent any reports. Use Report on a project, profile, case study, message or contract when something is wrong.',
                                        )}
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
