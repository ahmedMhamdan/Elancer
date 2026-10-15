import { Link, usePage } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import type { ReactNode } from 'react';
import ElancerSiteHeader from '@/components/elancer-site-header';
import HoverFooter from '@/components/hover-footer';
import SaveToggle from '@/components/save-toggle';
import { useTranslation } from '@/hooks/use-translation';
import type { PaginationData } from '@/components/tailadmin/pagination';
import '../../../css/elancer-jobs.css';

export type Skill = { id: number; name: string };
export type Category = {
    id: number;
    slug: string;
    categoryname: string;
    open_projects_count?: number;
};
export type Page<T> = PaginationData & { data: T[] };
export type Job = {
    id: number;
    title: string;
    excerpt: string;
    description?: string;
    category: Category;
    skills: Skill[];
    budget_min: string;
    budget_max: string;
    published_at: string;
    application_closes_at: string;
    open: boolean;
    proposals_received: number;
    screening_questions?: string[];
    // Q11: how many files the brief carries. The number is public; the files are not.
    attachments?: number;
    // Q08: present only where the viewer's own saved state is known.
    saved?: boolean;
    own?: boolean;
};
export function DiscoveryLayout({ children }: { children: ReactNode }) {
    return (
        <div className="elancer-home jobs-site">
            <ElancerSiteHeader />
            <main id="main-content" className="jobs-main">
                {children}
            </main>
            <HoverFooter />
        </div>
    );
}
export function Money({ min, max }: { min: string; max: string }) {
    const { locale } = useTranslation();
    const format = (value: string) =>
        new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 2,
        }).format(Number(value));
    return (
        <bdi>
            {format(min)}
            {Number(min) !== Number(max) ? ` – ${format(max)}` : ''}
        </bdi>
    );
}
export function JobDate({ value }: { value: string }) {
    const { locale } = useTranslation();
    return (
        <time dateTime={value}>
            {new Intl.DateTimeFormat(locale, {
                dateStyle: 'medium',
                timeZone: 'UTC',
            }).format(new Date(value))}
        </time>
    );
}
export type Attachment = { id: number; name: string; size: number };
export function FileSize({ bytes }: { bytes: number }) {
    const { locale } = useTranslation();
    const step = bytes < 1024 ? 0 : bytes < 1048576 ? 1 : 2;
    return (
        <bdi>
            {new Intl.NumberFormat(locale, {
                style: 'unit',
                unit: ['byte', 'kilobyte', 'megabyte'][step],
                maximumFractionDigits: 1,
            }).format(bytes / 1024 ** step)}
        </bdi>
    );
}
export function AttachmentCount({ count }: { count: number }) {
    const { t } = useTranslation();
    return (
        <>
            <Paperclip size={14} aria-hidden="true" />
            {count === 1
                ? t('1 attached file')
                : t(':count attached files', { count })}
        </>
    );
}
export function JobCard({ job, search }: { job: Job; search: string }) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    return (
        <article className="job-card">
            <div className="job-card-top">
                <Link
                    href={`/jobs?category=${encodeURIComponent(job.category.slug)}`}
                    className="job-category"
                    dir="auto"
                >
                    {job.category.categoryname}
                </Link>
                <span className="job-muted">
                    {t('Posted')} <JobDate value={job.published_at} />
                </span>
                {auth.user && job.saved !== undefined && !job.own && (
                    <SaveToggle
                        url={`/jobs/${job.id}/save`}
                        saved={job.saved}
                        name={job.title}
                        only={['projects']}
                    />
                )}
            </div>
            <h2>
                <Link
                    href={`/jobs/${job.id}?${new URLSearchParams({ search })}`}
                    onClick={() => {
                        try {
                            sessionStorage.setItem(
                                'elancer-search-position',
                                JSON.stringify({
                                    url: '/jobs' + (search ? '?' + search : ''),
                                    y: window.scrollY,
                                }),
                            );
                        } catch {
                            /* Browser Back still restores its own scroll state. */
                        }
                    }}
                    dir="auto"
                >
                    {job.title}
                </Link>
            </h2>
            <p dir="auto" className="job-excerpt">
                {job.excerpt}
            </p>
            <div className="job-skills">
                {job.skills.map((skill) => (
                    <span key={skill.id} dir="auto">
                        {skill.name}
                    </span>
                ))}
            </div>
            <div className="job-card-bottom">
                <div>
                    <strong>
                        <Money min={job.budget_min} max={job.budget_max} />
                    </strong>
                    <span className="job-muted">{t('Fixed-price budget')}</span>
                </div>
                <div className="job-card-status">
                    <span className={job.open ? 'job-open' : 'job-muted'}>
                        {t(
                            job.open
                                ? 'Open for applications'
                                : 'Applications closed',
                        )}
                    </span>
                    <span className="job-muted">
                        {job.proposals_received === 1
                            ? t('1 proposal received')
                            : t(':count proposals received', {
                                  count: job.proposals_received,
                              })}
                    </span>
                    {!!job.attachments && (
                        <span className="job-muted job-attachment-count">
                            <AttachmentCount count={job.attachments} />
                        </span>
                    )}
                </div>
            </div>
        </article>
    );
}
