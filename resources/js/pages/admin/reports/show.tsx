// Uses the adapted TailAdmin ComponentCard, Alert, Radio, TextArea and Button
// (MIT: THIRD_PARTY_NOTICES.md).
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import ComponentCard from '@/components/component-card';
import InputError from '@/components/input-error';
import Alert from '@/components/tailadmin/alert';
import Button from '@/components/tailadmin/button';
import Label from '@/components/tailadmin/label';
import Radio from '@/components/tailadmin/radio';
import TextArea from '@/components/tailadmin/textarea';
import { useReportLabels } from '@/hooks/use-report-labels';
import { useTranslation } from '@/hooks/use-translation';

type Person = { id?: number; name: string; email: string; status?: string };
type Revision = { body: string; created_at: string; rating?: number };
type Target = {
    title?: string;
    summary?: string | null;
    text?: string | null;
    href?: string | null;
    hidden?: boolean;
    sender?: 'client' | 'freelancer';
    created_at?: string;
    edited_at?: string | null;
    revisions?: Revision[];
    status?: string;
    client?: string | null;
    freelancer?: string | null;
    scope?: string;
    amount?: string | null;
    funded_at?: string | null;
    completed_at?: string | null;
    cancelled_at?: string | null;
    reviews?: {
        id: number;
        author: 'client' | 'freelancer';
        rating: number;
        body: string;
        revisions: Revision[];
    }[];
};
type Props = {
    report: {
        id: number;
        target_type: string;
        reason: string;
        explanation: string;
        status: string;
        outcome: string | null;
        snapshot: { title?: string; summary?: string; text?: string };
        created_at: string;
        resolved_at: string | null;
        reporter: Person | null;
        subject: Person | null;
        handler: string | null;
        subject_reports: number;
    };
    target: Target | null;
    notes: {
        id: number;
        body: string;
        author: string | null;
        created_at: string;
    }[];
    events: {
        id: number;
        action: string;
        actor: string | null;
        reason: string | null;
        created_at: string;
    }[];
    moderation: {
        hidden: boolean;
        at: string | null;
        by: string | null;
    } | null;
    can: {
        start: boolean;
        take: boolean;
        resolve: boolean;
        conversation: boolean;
        hide: boolean;
        restore: boolean;
    };
    notice: 'started' | 'resolved' | 'noted' | 'hidden' | 'restored' | null;
};

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="wrap-anywhere">{children}</dd>
        </div>
    );
}

function Prose({ children }: { children: ReactNode }) {
    return (
        <p dir="auto" className="wrap-anywhere whitespace-pre-wrap">
            {children}
        </p>
    );
}

export default function ReportPage({
    report,
    target,
    notes,
    events,
    moderation,
    can,
    notice,
}: Props) {
    const { t } = useTranslation();
    const { targets, reasons, statuses, outcomes, time } = useReportLabels();
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const resolve = useForm({ outcome: '', reason: '' });
    const note = useForm({ body: '' });
    const moderate = useForm({ reason: '' });
    const failed = () => {
        setError(
            t('The report changed or the request failed. Reload the page.'),
        );
        return false;
    };
    const roles = { client: t('Client'), freelancer: t('Freelancer') };
    const notices = {
        started: t('Review started. The reporter now sees In review.'),
        resolved: t('Report resolved.'),
        noted: t('Note added.'),
        hidden: t('Content hidden.'),
        restored: t('Content restored as it was.'),
    };
    // What hiding does, in the words of the thing that was reported.
    const effects: Record<string, string> = {
        message: t(
            'Both participants see a notice in place of the message. Its text and correction history stay here as evidence, and it can no longer be corrected.',
        ),
        project: t(
            'The project leaves public pages and lists, direct links included. Its owner sees that it was hidden. Proposals and contracts already under way keep working.',
        ),
        case: t(
            'The case study leaves public pages, direct links included. Its owner sees that it was hidden and cannot show it again.',
        ),
    };
    const choices: Record<string, string> = {
        action_taken: t('Action taken'),
        no_violation: t('No breach found'),
        not_confirmed: t('Could not be confirmed'),
        duplicate: t('Duplicate of another report'),
    };
    const actions: Record<string, string> = {
        review_started: t('Review started'),
        review_taken_over: t('Review taken over'),
        resolved: t('Resolved'),
        note_added: t('Note added'),
        conversation_read: t('Conversation read'),
        content_hidden: t('Content hidden'),
        content_restored: t('Content restored'),
        account_suspended: t('Account suspended'),
        account_reinstated: t('Account reinstated'),
    };
    const person = (value: Person | null) =>
        value ? (
            <>
                <bdi>{value.name}</bdi>{' '}
                <span className="text-muted-foreground text-sm">
                    <bdi dir="ltr">{value.email}</bdi>
                </span>
            </>
        ) : (
            t('Closed account')
        );
    const start = () => {
        setBusy(true);
        setError('');
        router.patch(
            `/admin/reports/${report.id}`,
            { action: 'start' },
            {
                preserveScroll: true,
                onHttpException: failed,
                onFinish: () => setBusy(false),
            },
        );
    };
    return (
        <div className="workspace-dashboard space-y-6">
            <Head title={t('Report #:id', { id: report.id })} />
            <Link
                href="/admin/reports"
                className="text-primary focus-visible:outline-ring inline-flex min-h-11 items-center gap-2 font-medium underline-offset-4 hover:underline focus-visible:outline-2"
            >
                <ArrowLeft
                    size={17}
                    className="rtl:rotate-180"
                    aria-hidden="true"
                />
                {t('Back to reports')}
            </Link>
            <div className="workspace-page-heading">
                <h1>{t('Report #:id', { id: report.id })}</h1>
                <p>
                    {targets[report.target_type]} · {statuses[report.status]}
                    {report.handler && (
                        <>
                            {' · '}
                            <bdi>{report.handler}</bdi>
                        </>
                    )}
                </p>
            </div>
            {notice && <Alert message={notices[notice]} />}
            {error && <Alert variant="error" message={error} />}
            <ComponentCard title={t('What the member reported')}>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <Fact label={t('Reported by')}>
                        {person(report.reporter)}
                    </Fact>
                    <Fact label={t('Reported member')}>
                        {person(report.subject)}
                        {report.subject?.status &&
                            report.subject.status !== 'active' && (
                                <span className="text-muted-foreground text-sm">
                                    {' · '}
                                    {t(
                                        report.subject.status === 'suspended'
                                            ? 'Suspended'
                                            : 'Deactivated',
                                    )}
                                </span>
                            )}
                        {report.subject?.id !== undefined && (
                            <Link
                                href={`/admin/accounts/${report.subject.id}?report=${report.id}`}
                                className="text-primary flex min-h-11 items-center text-sm font-medium underline underline-offset-4"
                            >
                                {t('Open their account')}
                            </Link>
                        )}
                    </Fact>
                    <Fact label={t('Reason')}>{reasons[report.reason]}</Fact>
                    <Fact label={t('Sent')}>
                        <time dateTime={report.created_at}>
                            {time(report.created_at)}
                        </time>
                    </Fact>
                </dl>
                <div>
                    <p className="text-muted-foreground text-sm">
                        {t('What happened?')}
                    </p>
                    <Prose>{report.explanation}</Prose>
                </div>
                {report.subject_reports > 0 && (
                    <p className="text-sm">
                        {t('Other reports about this member: :count', {
                            count: report.subject_reports,
                        })}
                    </p>
                )}
            </ComponentCard>
            <ComponentCard
                title={t('Reported content')}
                desc={t(
                    'Contract files, payment details and identity documents are never shown here.',
                )}
            >
                {report.snapshot.text !== undefined && (
                    <section className="space-y-2">
                        <h3 className="font-medium">
                            {t('When it was reported')}
                        </h3>
                        <p className="font-medium wrap-anywhere">
                            <bdi>{report.snapshot.title}</bdi>
                        </p>
                        {report.snapshot.summary && (
                            <Prose>{report.snapshot.summary}</Prose>
                        )}
                        <Prose>{report.snapshot.text}</Prose>
                    </section>
                )}
                {!target ? (
                    <p className="text-muted-foreground">
                        {t('This content is no longer available.')}
                    </p>
                ) : report.target_type === 'message' ? (
                    <section className="space-y-2">
                        <p className="text-muted-foreground text-sm">
                            {target.sender && roles[target.sender]}
                            {target.created_at && (
                                <>
                                    {' · '}
                                    <time dateTime={target.created_at}>
                                        {time(target.created_at)}
                                    </time>
                                </>
                            )}
                            {target.edited_at && <> · {t('Edited')}</>}
                        </p>
                        <Prose>{target.text}</Prose>
                        <History revisions={target.revisions ?? []} />
                    </section>
                ) : report.target_type === 'contract' ? (
                    <section className="space-y-4">
                        <p className="font-medium wrap-anywhere">
                            <bdi>{target.title}</bdi>
                        </p>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Fact label={t('Client')}>
                                <bdi>{target.client}</bdi>
                            </Fact>
                            <Fact label={t('Freelancer')}>
                                <bdi>{target.freelancer}</bdi>
                            </Fact>
                            <Fact label={t('Status')}>
                                <bdi dir="ltr">{target.status}</bdi>
                            </Fact>
                            <Fact label={t('Agreed amount')}>
                                <bdi dir="ltr">USD {target.amount}</bdi>
                            </Fact>
                        </dl>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                {t('Agreed scope')}
                            </p>
                            <Prose>{target.scope}</Prose>
                        </div>
                        {(target.reviews ?? []).map((review) => (
                            <div
                                key={review.id}
                                className="border-border space-y-2 border-t pt-4"
                            >
                                <p className="text-muted-foreground text-sm">
                                    {t('Review by the :role', {
                                        role: roles[review.author],
                                    })}
                                    {' · '}
                                    {review.rating}/5
                                </p>
                                <Prose>{review.body}</Prose>
                                <History revisions={review.revisions} />
                            </div>
                        ))}
                    </section>
                ) : (
                    <section className="space-y-2">
                        <h3 className="font-medium">{t('As it is now')}</h3>
                        <p className="font-medium wrap-anywhere">
                            <bdi>{target.title}</bdi>
                        </p>
                        {target.summary && <Prose>{target.summary}</Prose>}
                        <Prose>{target.text}</Prose>
                        {target.href ? (
                            <Link
                                href={target.href}
                                className="text-primary inline-flex min-h-11 items-center underline underline-offset-4"
                            >
                                {t('Open the public page')}
                            </Link>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                {t('It is not public right now.')}
                            </p>
                        )}
                    </section>
                )}
            </ComponentCard>
            {moderation && (
                <ComponentCard
                    title={t('Hide or restore')}
                    desc={effects[report.target_type]}
                >
                    <p className="font-medium">
                        {moderation.hidden
                            ? t('Hidden by moderation')
                            : t('Not hidden')}
                        {moderation.hidden && moderation.at && (
                            <span className="text-muted-foreground text-sm font-normal">
                                {' · '}
                                <bdi>
                                    {moderation.by ?? t('Closed account')}
                                </bdi>
                                {' · '}
                                <time dateTime={moderation.at}>
                                    {time(moderation.at)}
                                </time>
                            </span>
                        )}
                    </p>
                    {can.hide || can.restore ? (
                        <form
                            className="space-y-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (
                                    !window.confirm(
                                        can.hide
                                            ? t('Hide this content?')
                                            : t(
                                                  'Restore this content as it was?',
                                              ),
                                    )
                                )
                                    return;
                                setError('');
                                moderate.transform((data) => ({
                                    ...data,
                                    action: can.hide ? 'hide' : 'restore',
                                }));
                                moderate.patch(`/admin/reports/${report.id}`, {
                                    preserveScroll: true,
                                    onSuccess: () => moderate.reset(),
                                    onHttpException: failed,
                                });
                            }}
                        >
                            <div>
                                <Label htmlFor="moderation-reason">
                                    {t(
                                        'Internal reason (kept in the audit log)',
                                    )}
                                </Label>
                                <TextArea
                                    id="moderation-reason"
                                    rows={3}
                                    required
                                    minLength={5}
                                    maxLength={1000}
                                    dir="auto"
                                    placeholder=""
                                    value={moderate.data.reason}
                                    onChange={(value) =>
                                        moderate.setData('reason', value)
                                    }
                                />
                                <InputError message={moderate.errors.reason} />
                            </div>
                            <Button
                                type="submit"
                                variant={can.hide ? 'danger' : 'primary'}
                                disabled={
                                    moderate.processing ||
                                    moderate.data.reason.trim().length < 5
                                }
                            >
                                {can.hide
                                    ? t('Hide content')
                                    : t('Restore content')}
                            </Button>
                        </form>
                    ) : (
                        report.status !== 'resolved' && (
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Only the administrator reviewing this report can hide or restore its content.',
                                )}
                            </p>
                        )
                    )}
                </ComponentCard>
            )}
            {report.status !== 'resolved' ? (
                <ComponentCard
                    title={t('Review')}
                    desc={t(
                        'Resolving records an outcome for the reporter. It never completes, cancels or refunds a contract.',
                    )}
                >
                    {(can.start || can.take) && (
                        <div className="space-y-3">
                            {can.take && (
                                <p>
                                    {t(
                                        ':name is reviewing this report. Take it over only if they cannot finish it.',
                                        { name: report.handler ?? '' },
                                    )}
                                </p>
                            )}
                            <Button disabled={busy} onClick={start}>
                                {can.start
                                    ? t('Start review')
                                    : t('Take over the review')}
                            </Button>
                            {(report.target_type === 'message' ||
                                report.target_type === 'contract') && (
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'The reported conversation can be read once you are the reviewer.',
                                    )}
                                </p>
                            )}
                        </div>
                    )}
                    {can.conversation && (
                        <div>
                            <Link
                                href={`/admin/reports/${report.id}/conversation`}
                                className="text-primary inline-flex min-h-11 items-center font-medium underline underline-offset-4"
                            >
                                {t('Read the reported conversation')}
                            </Link>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'Every time you open it, the read is recorded in the audit log.',
                                )}
                            </p>
                        </div>
                    )}
                    {can.resolve && (
                        <form
                            className="space-y-5"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (
                                    !window.confirm(
                                        t(
                                            'Resolve this report? The reporter will see the outcome.',
                                        ),
                                    )
                                )
                                    return;
                                setError('');
                                resolve.transform((data) => ({
                                    ...data,
                                    action: 'resolve',
                                }));
                                resolve.patch(`/admin/reports/${report.id}`, {
                                    preserveScroll: true,
                                    onHttpException: failed,
                                });
                            }}
                        >
                            <fieldset className="space-y-3">
                                <legend className="mb-2 font-medium">
                                    {t('Outcome')}
                                </legend>
                                {Object.entries(choices).map(
                                    ([value, text]) => (
                                        <Radio
                                            key={value}
                                            id={'outcome-' + value}
                                            name="outcome"
                                            value={value}
                                            label={text}
                                            checked={
                                                resolve.data.outcome === value
                                            }
                                            onChange={(choice) =>
                                                resolve.setData(
                                                    'outcome',
                                                    choice,
                                                )
                                            }
                                        />
                                    ),
                                )}
                                <InputError message={resolve.errors.outcome} />
                                {resolve.data.outcome && (
                                    <p
                                        role="status"
                                        className="text-muted-foreground text-sm"
                                    >
                                        {t('The reporter will read:')}{' '}
                                        {outcomes[resolve.data.outcome]}
                                    </p>
                                )}
                            </fieldset>
                            <div>
                                <Label htmlFor="resolve-reason">
                                    {t(
                                        'Internal reason (kept in the audit log)',
                                    )}
                                </Label>
                                <TextArea
                                    id="resolve-reason"
                                    rows={3}
                                    required
                                    minLength={5}
                                    maxLength={1000}
                                    dir="auto"
                                    placeholder=""
                                    value={resolve.data.reason}
                                    onChange={(value) =>
                                        resolve.setData('reason', value)
                                    }
                                />
                                <InputError message={resolve.errors.reason} />
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    resolve.processing ||
                                    !resolve.data.outcome ||
                                    resolve.data.reason.trim().length < 5
                                }
                            >
                                {t('Resolve report')}
                            </Button>
                        </form>
                    )}
                </ComponentCard>
            ) : (
                <ComponentCard title={t('Outcome')}>
                    <p className="font-medium">
                        {report.outcome && choices[report.outcome]}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        {t('The reporter will read:')}{' '}
                        {report.outcome && outcomes[report.outcome]}
                    </p>
                    {report.resolved_at && (
                        <p className="text-muted-foreground text-sm">
                            <time dateTime={report.resolved_at}>
                                {time(report.resolved_at)}
                            </time>
                        </p>
                    )}
                </ComponentCard>
            )}
            <ComponentCard
                title={t('Internal notes')}
                desc={t(
                    'Only administrators see these. They are never shown to the reporter or the reported member.',
                )}
            >
                {notes.length ? (
                    <ol className="space-y-4">
                        {notes.map((item) => (
                            <li key={item.id}>
                                <p className="text-muted-foreground text-sm">
                                    <bdi>
                                        {item.author ?? t('Closed account')}
                                    </bdi>
                                    {' · '}
                                    <time dateTime={item.created_at}>
                                        {time(item.created_at)}
                                    </time>
                                </p>
                                <Prose>{item.body}</Prose>
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="text-muted-foreground">
                        {t('No notes yet.')}
                    </p>
                )}
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setError('');
                        note.post(`/admin/reports/${report.id}/notes`, {
                            preserveScroll: true,
                            onSuccess: () => note.reset(),
                            onHttpException: failed,
                        });
                    }}
                >
                    <Label htmlFor="report-note">{t('Add a note')}</Label>
                    <TextArea
                        id="report-note"
                        rows={3}
                        maxLength={2000}
                        dir="auto"
                        placeholder=""
                        value={note.data.body}
                        onChange={(value) => note.setData('body', value)}
                    />
                    <InputError message={note.errors.body} />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={
                            note.processing || note.data.body.trim().length < 2
                        }
                    >
                        {t('Save note')}
                    </Button>
                </form>
            </ComponentCard>
            <ComponentCard title={t('Audit log')}>
                {events.length ? (
                    <ol className="space-y-3">
                        {events.map((event) => (
                            <li key={event.id}>
                                <p>
                                    <span className="font-medium">
                                        {actions[event.action] ?? event.action}
                                    </span>
                                    {' · '}
                                    <bdi>
                                        {event.actor ?? t('Closed account')}
                                    </bdi>
                                    {' · '}
                                    <time
                                        dateTime={event.created_at}
                                        className="text-muted-foreground text-sm whitespace-nowrap"
                                    >
                                        {time(event.created_at)}
                                    </time>
                                </p>
                                {event.reason && <Prose>{event.reason}</Prose>}
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="text-muted-foreground">
                        {t('Nothing has been done on this report yet.')}
                    </p>
                )}
            </ComponentCard>
        </div>
    );
}

function History({ revisions }: { revisions: Revision[] }) {
    const { t } = useTranslation();
    const { time } = useReportLabels();
    if (!revisions.length) return null;
    return (
        <details className="text-sm">
            <summary className="focus-visible:outline-ring inline-flex min-h-11 cursor-pointer items-center underline underline-offset-4 focus-visible:outline-2">
                {t('Earlier versions (:count)', { count: revisions.length })}
            </summary>
            <ol className="border-border mt-2 space-y-3 border-s ps-4">
                {revisions.map((revision, index) => (
                    <li key={index}>
                        <p className="text-muted-foreground">
                            <time dateTime={revision.created_at}>
                                {time(revision.created_at)}
                            </time>
                            {revision.rating !== undefined && (
                                <> · {revision.rating}/5</>
                            )}
                        </p>
                        <Prose>{revision.body}</Prose>
                    </li>
                ))}
            </ol>
        </details>
    );
}
