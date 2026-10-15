import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import type { Freelancer, WorkLink } from '@/components/freelancer-card';
import { AttachedFiles } from '@/components/attached-files';
import { CaseBody, type CaseContent } from '@/components/portfolio-case';
import { Money } from '@/pages/discovery/shared';
import type { Attachment } from '@/pages/discovery/shared';
import '../../../css/elancer-marketplace.css';
export type ProposalContent = {
    price: string | null;
    duration_days: number | null;
    message: string;
    answers: string[];
    samples: WorkLink[];
    // Plan 030: frozen into each submitted version; absent from versions sent before files existed.
    files?: Attachment[];
    // Plan 031: the chosen case studies, and as the server resolved them for this reader.
    cases?: number[];
    case_samples?: CaseSample[];
};
// A copy sent with a version (id), or the author's preview of a draft's choice (id null).
// A client receives only {id, withheld: true} for a copy that is withheld under Q55.
export type CaseSample = Partial<CaseContent> & {
    id: number | null;
    case_id?: number | null;
    withheld: boolean;
    elancer_work?: boolean;
};
export type ProposalProject = {
    id: number;
    title: string;
    budget_min: string;
    budget_max: string;
    screening_questions: string[] | null;
    application_closes_at: string;
};
export type Proposal = {
    id: number;
    project_id: number;
    status: string;
    version: number;
    organization?: string;
    client_note?: string | null;
    draft?: ProposalContent | null;
    content: ProposalContent | null;
    profile_snapshot: Freelancer | null;
    submitted_at: string | null;
    // The author's only: the files of the next version, and whether they differ from the one the client has.
    files?: Attachment[];
    files_changed?: boolean;
    events: {
        kind: string;
        content: ProposalContent | null;
        created_at: string;
    }[];
};
export function ProposalLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <AppLayout breadcrumbs={[{ title, href: '/my-proposals' }]}>
            <main className="workspace-dashboard market-workspace">
                <header className="workspace-page-heading">
                    <h1>{title}</h1>
                </header>
                {children}
            </main>
        </AppLayout>
    );
}
export function ProposalStatus({ status }: { status: string }) {
    const { t } = useTranslation();
    const labels: Record<string, string> = {
        draft: t('Draft'),
        submitted: t('Submitted'),
        withdrawn: t('Withdrawn'),
        declined: t('Declined'),
        reopened: t('Reopened'),
    };
    return (
        <span className={`proposal-status proposal-status-${status}`}>
            {labels[status] ?? status}
        </span>
    );
}
export function ContentDetails({
    content,
    project,
    proposalId,
}: {
    content: ProposalContent;
    project: ProposalProject;
    proposalId: number;
}) {
    const { t } = useTranslation();
    return (
        <div className="market-stack">
            <dl className="proposal-terms">
                <div>
                    <dt>{t('Proposed price')}</dt>
                    <dd>
                        <Money
                            min={content.price ?? '0'}
                            max={content.price ?? '0'}
                        />
                    </dd>
                </div>
                <div>
                    <dt>{t('Delivery duration')}</dt>
                    <dd>
                        {t(':count calendar days', {
                            count: content.duration_days ?? 0,
                        })}
                    </dd>
                </div>
            </dl>
            <section>
                <h2>{t('Cover message')}</h2>
                <p className="market-prose" dir="auto">
                    {content.message}
                </p>
            </section>
            {(project.screening_questions ?? []).map((question, i) => (
                <section key={i}>
                    <h3 dir="auto">{question}</h3>
                    <p className="market-prose" dir="auto">
                        {content.answers[i]}
                    </p>
                </section>
            ))}
            {!!content.samples.length && (
                <section>
                    <h2>{t('Work samples')}</h2>
                    <ul className="talent-links">
                        {content.samples.map((sample, i) => (
                            <li key={i}>
                                <a
                                    href={sample.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    dir="auto"
                                >
                                    {sample.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            {!!content.case_samples?.length && (
                <section className="market-stack">
                    <h2>{t('Case studies')}</h2>
                    {content.case_samples.map((sample, i) =>
                        sample.title === undefined ? (
                            <p key={i} className="market-muted">
                                {t('This case study is no longer available.')}
                            </p>
                        ) : (
                            <article key={i} className="proposal-case">
                                <h3 dir="auto">{sample.title}</h3>
                                {sample.elancer_work && (
                                    <p className="talent-meta">
                                        <BadgeCheck
                                            size={16}
                                            aria-hidden="true"
                                        />
                                        {t(
                                            'Completed on Elancer and published with the approval of the client.',
                                        )}
                                    </p>
                                )}
                                {sample.withheld && (
                                    <p className="market-error">
                                        {t(
                                            'The client no longer sees this case study: its approval was withdrawn or it was hidden by moderation.',
                                        )}
                                    </p>
                                )}
                                <CaseBody
                                    content={sample as CaseContent}
                                    imageUrl={
                                        sample.id === null
                                            ? undefined
                                            : (image) =>
                                                  `/proposals/${proposalId}/cases/${sample.id}/images/${image}`
                                    }
                                />
                            </article>
                        ),
                    )}
                </section>
            )}
            {!!content.files?.length && (
                <section className="market-stack">
                    <h2>{t('Attached files')}</h2>
                    <AttachedFiles
                        files={content.files}
                        href={(file) =>
                            `/proposals/${proposalId}/files/${file.id}`
                        }
                    />
                </section>
            )}
        </div>
    );
}
export function ProjectContext({ project }: { project: ProposalProject }) {
    const { t } = useTranslation();
    return (
        <section className="market-panel">
            <h2>
                <Link href={`/jobs/${project.id}`} dir="auto">
                    {project.title}
                </Link>
            </h2>
            <p className="market-muted">{t('Advertised budget')}</p>
            <Money min={project.budget_min} max={project.budget_max} />
            <p className="market-muted">
                {t('Your proposal can be above or below this budget.')}
            </p>
        </section>
    );
}
