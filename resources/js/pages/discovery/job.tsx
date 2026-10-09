import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import ReportDialog from '@/components/report-dialog';
import Button from '@/components/tailadmin/button';
import DatePicker from '@/components/tailadmin/date-picker';
import TextArea from '@/components/tailadmin/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { DiscoveryLayout, JobDate, Money } from './shared';
import type { Job } from './shared';

// Q06/Q61: what the owner may still do to a published project. The brief itself is never edited.
function OwnerUpdates({
    project,
    can,
}: {
    project: Job;
    can: { extend: boolean; clarify: boolean };
}) {
    const { t } = useTranslation();
    const note = useForm({ body: '' });
    const cutoff = useForm({ application_closes_at: '' });
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    function clarify(event: FormEvent) {
        event.preventDefault();
        if (
            !window.confirm(
                t(
                    'Publish this clarification? It is public, people who applied are notified, and it cannot be edited or removed.',
                ),
            )
        )
            return;
        note.post(`/my-projects/${project.id}/clarifications`, {
            preserveScroll: true,
            onSuccess: () => note.reset(),
        });
    }
    function extend(event: FormEvent) {
        event.preventDefault();
        const chosen = new Date(cutoff.data.application_closes_at);
        cutoff.transform(() => ({
            application_closes_at: Number.isNaN(chosen.getTime())
                ? ''
                : chosen.toISOString(),
        }));
        cutoff.patch(`/my-projects/${project.id}/cutoff`, {
            preserveScroll: true,
            onSuccess: () => cutoff.reset(),
        });
    }
    return (
        <section
            id="project-updates"
            className="job-card mt-6"
            aria-labelledby="project-updates-heading"
        >
            <h2 id="project-updates-heading">{t('Update this project')}</h2>
            <p className="text-muted-foreground">
                {t(
                    'The brief, budget and screening questions stay as published. You can add a clarification or move the application cutoff later.',
                )}
            </p>
            {can.clarify && (
                <form onSubmit={clarify} className="mt-5 grid gap-2">
                    <label htmlFor="clarification-body">
                        {t('Add a clarification')}
                    </label>
                    <TextArea
                        id="clarification-body"
                        rows={4}
                        maxLength={2000}
                        dir="auto"
                        placeholder={t(
                            'Answer a question several applicants asked, or add a detail the brief missed.',
                        )}
                        value={note.data.body}
                        onChange={(value) => note.setData('body', value)}
                        error={!!note.errors.body}
                        aria-invalid={!!note.errors.body}
                        aria-describedby="clarification-help"
                    />
                    <small id="clarification-help">
                        {note.errors.body ??
                            t(
                                'Shown under the brief with today’s date. Between 10 and 2000 characters.',
                            )}
                    </small>
                    <div>
                        <Button type="submit" disabled={note.processing}>
                            {t('Publish clarification')}
                        </Button>
                    </div>
                </form>
            )}
            {can.extend && (
                // The picker keeps its own hour and minute fields inside this form; a typed minute such as 18
                // fails their five-minute step and would block the submit silently, so the server validates.
                <form onSubmit={extend} noValidate className="mt-6 grid gap-2">
                    <label htmlFor="extend-cutoff">
                        {t('Extend the application cutoff')}
                    </label>
                    <DatePicker
                        id="extend-cutoff"
                        time
                        min="today"
                        placeholder={t('Choose a date')}
                        value={cutoff.data.application_closes_at}
                        onChange={(value) =>
                            cutoff.setData('application_closes_at', value)
                        }
                    />
                    <small>
                        {cutoff.errors.application_closes_at ??
                            t(
                                'Choose a time later than the current cutoff. A later cutoff reopens applications.',
                            )}
                    </small>
                    <small>{t('Times shown in :timezone', { timezone })}</small>
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={
                                cutoff.processing ||
                                !cutoff.data.application_closes_at
                            }
                        >
                            {t('Extend cutoff')}
                        </Button>
                    </div>
                </form>
            )}
        </section>
    );
}

export default function JobDetails({
    project,
    client,
    returnUrl,
    application,
    moderated,
    clarifications,
    can,
}: {
    project: Job;
    // Only the owner reaches this page while moderation hides the project.
    moderated: boolean;
    clarifications: { id: number; body: string; created_at: string }[];
    can: { extend: boolean; clarify: boolean };
    client: {
        name: string;
        country: string | null;
        member_since: string | null;
    };
    returnUrl: string;
    application: {
        owner: boolean;
        proposal: { id: number; status: string } | null;
    };
}) {
    const { t, locale } = useTranslation();
    const { auth } = usePage().props;
    return (
        <DiscoveryLayout>
            <Head title={project.title} />
            <Link href={returnUrl} preserveScroll className="job-back">
                <ArrowLeft size={18} className="rtl:rotate-180" />
                {t('Back to results')}
            </Link>
            {moderated && (
                <p
                    role="status"
                    className="border-border bg-card mb-6 rounded-xl border p-4"
                >
                    {t(
                        'This project was hidden by moderation. It is not on public pages or in search, and only you can open this page. Proposals and contracts already under way keep working.',
                    )}
                </p>
            )}
            <header className="jobs-heading">
                <div>
                    <Link
                        className="job-category"
                        href={`/jobs?category=${encodeURIComponent(project.category.slug)}`}
                        dir="auto"
                    >
                        {project.category.categoryname}
                    </Link>
                    <h1 dir="auto">{project.title}</h1>
                    <p>
                        {t('Posted')} <JobDate value={project.published_at} />
                    </p>
                </div>
                <span className={project.open ? 'job-open' : 'job-muted'}>
                    {t(
                        project.open
                            ? 'Open for applications'
                            : 'Applications closed',
                    )}
                </span>
            </header>
            <div className="job-detail-grid">
                <article className="job-card">
                    <h2>{t('Project brief')}</h2>
                    <p className="job-description" dir="auto">
                        {project.description}
                    </p>
                    {clarifications.length > 0 && (
                        <>
                            <h2>{t('Clarifications from the client')}</h2>
                            <ol className="grid gap-4">
                                {clarifications.map((note) => (
                                    <li key={note.id}>
                                        <p className="text-muted-foreground text-sm">
                                            {t('Added')}{' '}
                                            <JobDate value={note.created_at} />
                                        </p>
                                        <p
                                            dir="auto"
                                            className="mt-1 break-words whitespace-pre-wrap"
                                        >
                                            {note.body}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </>
                    )}
                    <h2>{t('Required skills')}</h2>
                    <div className="job-skills">
                        {project.skills.map((s) => (
                            <span key={s.id} dir="auto">
                                {s.name}
                            </span>
                        ))}
                    </div>
                    {!!project.screening_questions?.length && (
                        <>
                            <h2>{t('Screening questions')}</h2>
                            <ol>
                                {project.screening_questions.map((q, i) => (
                                    <li key={i} dir="auto">
                                        {q}
                                    </li>
                                ))}
                            </ol>
                        </>
                    )}
                </article>
                <aside className="job-card job-detail-meta">
                    <h2>{t('Fixed-price budget')}</h2>
                    <strong>
                        <Money
                            min={project.budget_min}
                            max={project.budget_max}
                        />
                    </strong>
                    <p>
                        {t(
                            'The advertised budget is a guide. Final terms are agreed with the freelancer.',
                        )}
                    </p>
                    <hr />
                    <h2>{t('Application cutoff')}</h2>
                    <p>
                        {new Intl.DateTimeFormat(locale, {
                            dateStyle: 'long',
                            timeStyle: 'short',
                            timeZone: 'UTC',
                        }).format(new Date(project.application_closes_at))}{' '}
                        (UTC)
                    </p>
                    <p>
                        {t(
                            'This is the deadline to apply, not the delivery date.',
                        )}
                    </p>
                    <hr />
                    <h2>{t('About the client')}</h2>
                    <p dir="auto">{client.name}</p>
                    {client.country && <p dir="auto">{client.country}</p>}
                    {client.member_since && (
                        <p>
                            {t('Member since :year', {
                                year: client.member_since,
                            })}
                        </p>
                    )}
                    <hr />
                    <p>
                        {t(':count proposals received', {
                            count: project.proposals_received,
                        })}
                    </p>
                    {application.owner ? (
                        <Link
                            className="job-post-link"
                            href={`/my-projects/${project.id}/proposals`}
                        >
                            {t('Review applicants')}
                        </Link>
                    ) : application.proposal ? (
                        <Link
                            className="job-post-link"
                            href={`/proposals/${application.proposal.id}`}
                        >
                            {t('View your proposal')}
                        </Link>
                    ) : (
                        project.open && (
                            <Link
                                className="job-post-link"
                                href={
                                    auth.user
                                        ? `/jobs/${project.id}/apply`
                                        : '/login'
                                }
                            >
                                {t(
                                    auth.user
                                        ? 'Apply to this project'
                                        : 'Log in to apply',
                                )}
                            </Link>
                        )
                    )}
                    {auth.user && !application.owner && (
                        <ReportDialog
                            type="project"
                            id={project.id}
                            label={t('Report this project')}
                        />
                    )}
                </aside>
            </div>
            {(can.extend || can.clarify) && (
                <OwnerUpdates project={project} can={can} />
            )}
        </DiscoveryLayout>
    );
}
