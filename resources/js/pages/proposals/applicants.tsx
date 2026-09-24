import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { Money, type Page } from '@/pages/discovery/shared';
import {
    ProposalLayout,
    ProposalStatus,
    type Proposal,
    type ProposalProject,
} from './shared';
export default function Applicants({
    project,
    proposals,
    filters,
}: {
    project: ProposalProject;
    proposals: Page<Proposal>;
    filters: { sort: string; organization: string };
}) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<number[]>([]);
    const labels: Record<string, string> = {
        received: t('Received'),
        shortlisted: t('Shortlisted'),
        archived: t('Archived'),
    };
    const filter = (key: string, value: string) => {
        setSelected([]);
        router.get(
            `/my-projects/${project.id}/proposals`,
            { ...filters, [key]: value },
            { preserveState: true },
        );
    };
    return (
        <ProposalLayout title={t('Project applicants')}>
            <Head title={t('Project applicants')} />
            <div className="market-stack">
                <h2 dir="auto">{project.title}</h2>
                <Link href="/my-projects">{t('My projects')}</Link>
                <div className="market-actions">
                    <label className="market-field">
                        <span>{t('Sort applicants')}</span>
                        <select
                            value={filters.sort}
                            onChange={(e) => filter('sort', e.target.value)}
                        >
                            <option value="newest">{t('Newest first')}</option>
                            <option value="price">{t('Lowest price')}</option>
                            <option value="duration">
                                {t('Shortest duration')}
                            </option>
                        </select>
                    </label>
                    <label className="market-field">
                        <span>{t('Organize applicant')}</span>
                        <select
                            value={filters.organization}
                            onChange={(e) =>
                                filter('organization', e.target.value)
                            }
                        >
                            <option value="">{t('All applicants')}</option>
                            {Object.entries(labels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button
                        disabled={!selected.length}
                        onClick={() =>
                            router.get(
                                `/my-projects/${project.id}/proposals/compare`,
                                { ids: selected },
                            )
                        }
                    >
                        {t('Compare selected (:count)', {
                            count: selected.length,
                        })}
                    </Button>
                </div>
                <p className="market-muted">
                    {t(
                        'Select up to three applicants to compare. Drafts are never visible here.',
                    )}
                </p>
                {!proposals.data.length && (
                    <section className="market-panel">
                        <h2>{t('No applicants here yet')}</h2>
                        <p>
                            {t(
                                'Submitted proposals will appear here for your review.',
                            )}
                        </p>
                    </section>
                )}
                {proposals.data.map((proposal) => (
                    <article className="market-panel" key={proposal.id}>
                        <div className="market-actions">
                            <label className="market-actions">
                                <input
                                    type="checkbox"
                                    checked={selected.includes(proposal.id)}
                                    disabled={
                                        selected.length >= 3 &&
                                        !selected.includes(proposal.id)
                                    }
                                    onChange={(e) =>
                                        setSelected(
                                            e.target.checked
                                                ? [...selected, proposal.id]
                                                : selected.filter(
                                                      (id) =>
                                                          id !== proposal.id,
                                                  ),
                                        )
                                    }
                                />
                                <span>{t('Compare')}</span>
                            </label>
                            <ProposalStatus status={proposal.status} />
                            <span>
                                {labels[proposal.organization ?? 'received']}
                            </span>
                        </div>
                        <h2>
                            <Link href={`/proposals/${proposal.id}`} dir="auto">
                                {proposal.profile_snapshot?.name}
                            </Link>
                        </h2>
                        <p dir="auto">{proposal.profile_snapshot?.headline}</p>
                        <div className="market-actions">
                            <Money
                                min={proposal.content?.price ?? '0'}
                                max={proposal.content?.price ?? '0'}
                            />
                            <span>
                                {t(':count calendar days', {
                                    count: proposal.content?.duration_days ?? 0,
                                })}
                            </span>
                        </div>
                        <p className="line-clamp-2" dir="auto">
                            {proposal.content?.message}
                        </p>
                        <Link href={`/proposals/${proposal.id}`}>
                            {t('Review proposal')}
                        </Link>
                    </article>
                ))}
                <Pagination data={proposals} />
            </div>
        </ProposalLayout>
    );
}
