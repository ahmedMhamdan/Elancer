// Adapted from TailAdmin src/components/tables/BasicTables/BasicTableOne.tsx through the existing
// table and pagination adaptations, with the two figures above it in the bordered card of
// src/components/ecommerce/EcommerceMetrics.tsx that Finance already adapts. Reuses TailAdmin Alert
// and Button. MIT: THIRD_PARTY_NOTICES.md.
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '@/components/tailadmin/alert';
import Button from '@/components/tailadmin/button';
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

type Failure = {
    id: number;
    queue: string;
    kind: 'email' | 'live' | 'other';
    name: string;
    error: string;
    failed_at: string;
};

export default function Operations({
    failures,
    queue,
    notice,
}: {
    failures: PaginationData & { data: Failure[] };
    queue: { waiting: number; oldest: string | null; failed: number };
    notice: 'retried' | 'unchanged' | 'dismissed' | null;
}) {
    const { t } = useTranslation();
    const { time } = useReportLabels();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const head =
        'text-muted-foreground px-4 py-3 text-start text-sm font-medium';
    const kinds = {
        email: t('Email about a workspace event'),
        live: t('Live update'),
        other: t('Other background work'),
    };
    const notices = {
        retried: t(
            'The work was put back on the queue. It runs when the worker reaches it.',
        ),
        unchanged: t(
            'The work could not be put back on the queue. It is still listed here.',
        ),
        dismissed: t('The failure was dismissed.'),
    };
    function act(failure: Failure, retry: boolean) {
        if (busy) return;
        const question = retry
            ? failure.kind === 'email'
                ? t(
                      'Retry this work? The email is sent now, however long ago it failed.',
                  )
                : t('Retry this work?')
            : t('Dismiss this failure? The work will not run.');
        if (!window.confirm(question)) return;
        const failed = () => {
            setError(t('The change could not be saved. Please try again.'));
            return false;
        };
        setBusy(true);
        setError('');
        router.visit(
            `/admin/operations/${failure.id}${retry ? '/retry' : ''}`,
            {
                method: retry ? 'post' : 'delete',
                preserveScroll: true,
                onError: failed,
                onNetworkError: failed,
                onHttpException: failed,
                onFinish: () => setBusy(false),
            },
        );
    }
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('Background work')} />
            <div className="workspace-page-heading">
                <h1>{t('Background work')}</h1>
                <p>
                    {t(
                        'Emails and live updates that failed after being queued. Only what kind of work it was and one line of the error are shown; the stored content is never displayed.',
                    )}
                </p>
            </div>
            {notice && (
                <Alert
                    variant={notice === 'unchanged' ? 'error' : 'success'}
                    message={notices[notice]}
                />
            )}
            {error && <Alert variant="error" message={error} />}
            <dl className="grid gap-4 sm:grid-cols-2">
                <div className="border-border bg-card rounded-xl border p-5">
                    <dt className="text-muted-foreground text-sm">
                        {t('Waiting in the queue')}
                    </dt>
                    <dd className="mt-2 text-2xl font-semibold tabular-nums">
                        {queue.waiting}
                    </dd>
                    <dd className="text-muted-foreground mt-1 text-sm">
                        {queue.oldest ? (
                            <>
                                {t('Oldest since')}{' '}
                                <time dateTime={queue.oldest}>
                                    {time(queue.oldest)}
                                </time>
                            </>
                        ) : (
                            t('Nothing is waiting.')
                        )}
                    </dd>
                </div>
                <div className="border-border bg-card rounded-xl border p-5">
                    <dt className="text-muted-foreground text-sm">
                        {t('Failed')}
                    </dt>
                    <dd className="mt-2 text-2xl font-semibold tabular-nums">
                        {queue.failed}
                    </dd>
                    <dd className="text-muted-foreground mt-1 text-sm">
                        {queue.failed
                            ? t(
                                  'Each stays here until it is retried or dismissed.',
                              )
                            : t('Background work is running normally.')}
                    </dd>
                </div>
            </dl>
            <div className="border-border bg-card overflow-hidden rounded-xl border">
                <TableScroll label={t('Failed work')}>
                    <Table className="w-full">
                        <TableHeader className="border-border border-b">
                            <TableRow>
                                <TableCell isHeader className={head}>
                                    {t('Failed at')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Work')}
                                </TableCell>
                                <TableCell isHeader className={head}>
                                    {t('Error')}
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
                            {failures.data.map((failure) => (
                                <TableRow key={failure.id}>
                                    <TableCell className="px-4 py-4 text-start whitespace-nowrap">
                                        <time dateTime={failure.failed_at}>
                                            {time(failure.failed_at)}
                                        </time>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 text-start">
                                        <span className="block min-w-48 font-medium">
                                            {kinds[failure.kind]}
                                        </span>
                                        <span className="text-muted-foreground mt-1 block text-sm wrap-anywhere">
                                            <bdi dir="ltr">{failure.name}</bdi>
                                            {' · '}
                                            {t('Queue')}{' '}
                                            <bdi dir="ltr">{failure.queue}</bdi>
                                        </span>
                                    </TableCell>
                                    <TableCell className="px-4 py-4 text-start">
                                        {/* Technical text is always left to right, whatever the page direction. */}
                                        <span
                                            dir="ltr"
                                            className="block max-w-xl min-w-64 text-sm wrap-anywhere"
                                        >
                                            {failure.error}
                                        </span>
                                    </TableCell>
                                    <TableCell className="w-px px-4 py-4 text-end">
                                        <div className="flex items-center justify-end gap-2 whitespace-nowrap">
                                            <Button
                                                size="sm"
                                                disabled={busy}
                                                onClick={() =>
                                                    act(failure, true)
                                                }
                                                aria-label={`${t('Retry')}: ${kinds[failure.kind]}, ${time(failure.failed_at)}`}
                                            >
                                                {t('Retry')}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="danger-outline"
                                                disabled={busy}
                                                onClick={() =>
                                                    act(failure, false)
                                                }
                                                aria-label={`${t('Dismiss')}: ${kinds[failure.kind]}, ${time(failure.failed_at)}`}
                                            >
                                                {t('Dismiss')}
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {!failures.data.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-muted-foreground px-5 py-12 text-center"
                                    >
                                        {t('Nothing has failed.')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </TableScroll>
            </div>
            <Pagination data={failures} />
        </div>
    );
}
