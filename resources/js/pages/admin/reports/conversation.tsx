// Uses the adapted TailAdmin Alert and pagination (MIT: THIRD_PARTY_NOTICES.md).
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Alert from '@/components/tailadmin/alert';
import Pagination, {
    type PaginationData,
} from '@/components/tailadmin/pagination';
import { useReportLabels } from '@/hooks/use-report-labels';
import { useTranslation } from '@/hooks/use-translation';

type Entry = {
    id: number;
    body: string;
    created_at: string;
    edited_at: string | null;
    sender: 'client' | 'freelancer';
    revisions: { body: string; version: number; created_at: string }[];
};

export default function ReportedConversation({
    report,
    conversation,
    messages,
}: {
    report: { id: number; reported_message: number | null };
    conversation: { project: string; client: string; freelancer: string };
    messages: PaginationData & { data: Entry[] };
}) {
    const { t } = useTranslation();
    const { time } = useReportLabels();
    const roles = { client: t('Client'), freelancer: t('Freelancer') };
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('Reported conversation')} />
            <Link
                href={`/admin/reports/${report.id}`}
                className="text-primary focus-visible:outline-ring inline-flex min-h-11 items-center gap-2 font-medium underline-offset-4 hover:underline focus-visible:outline-2"
            >
                <ArrowLeft
                    size={17}
                    className="rtl:rotate-180"
                    aria-hidden="true"
                />
                {t('Back to report #:id', { id: report.id })}
            </Link>
            <div className="workspace-page-heading">
                <h1>{t('Reported conversation')}</h1>
                <p>
                    <bdi>{conversation.project}</bdi>
                    {' · '}
                    {roles.client}: <bdi>{conversation.client}</bdi>
                    {' · '}
                    {roles.freelancer}: <bdi>{conversation.freelancer}</bdi>
                </p>
            </div>
            <Alert
                message={t(
                    'Opening this page was recorded in the audit log. Read only what the report needs.',
                )}
            />
            <ol className="space-y-4" aria-label={t('Message history')}>
                {messages.data.map((message) => (
                    <li
                        key={message.id}
                        className={`bg-card rounded-xl border p-4 ${
                            message.id === report.reported_message
                                ? 'border-red-500/60'
                                : 'border-border'
                        }`}
                    >
                        <p className="text-muted-foreground text-sm">
                            <span className="text-foreground font-medium">
                                {roles[message.sender]}
                                {': '}
                                <bdi>{conversation[message.sender]}</bdi>
                            </span>
                            {' · '}
                            <time dateTime={message.created_at}>
                                {time(message.created_at)}
                            </time>
                            {message.edited_at && <> · {t('Edited')}</>}
                            {message.id === report.reported_message && (
                                <strong className="text-red-700 dark:text-red-300">
                                    {' · '}
                                    {t('Reported message')}
                                </strong>
                            )}
                        </p>
                        <p
                            dir="auto"
                            className="mt-2 wrap-anywhere whitespace-pre-wrap"
                        >
                            {message.body}
                        </p>
                        {!!message.revisions.length && (
                            <details className="mt-2 text-sm">
                                <summary className="focus-visible:outline-ring inline-flex min-h-11 cursor-pointer items-center underline underline-offset-4 focus-visible:outline-2">
                                    {t('Correction history')}
                                </summary>
                                <ol className="border-border mt-2 space-y-3 border-s ps-4">
                                    {message.revisions.map((revision) => (
                                        <li key={revision.version}>
                                            <p className="text-muted-foreground">
                                                <time
                                                    dateTime={
                                                        revision.created_at
                                                    }
                                                >
                                                    {time(revision.created_at)}
                                                </time>
                                            </p>
                                            <p
                                                dir="auto"
                                                className="wrap-anywhere whitespace-pre-wrap"
                                            >
                                                {revision.body}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </details>
                        )}
                    </li>
                ))}
            </ol>
            <Pagination data={messages} />
        </div>
    );
}
