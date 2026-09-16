// Filter controls adapt local TailAdmin form-elements/DefaultInputs.tsx and MultiSelect.tsx.
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Pagination from '@/components/tailadmin/pagination';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/use-translation';
import { DiscoveryLayout, JobCard } from './shared';
import type { Category, Job, Page, Skill } from './shared';

type Filters = {
    q: string;
    category: string;
    skills: number[];
    budget_min: string;
    budget_max: string;
    posted: string;
    status: string;
    sort: string;
};
type Props = {
    projects: Page<Job>;
    filters: Filters;
    categories: Category[];
    skills: Skill[];
    canMatchSkills: boolean;
};
const empty: Filters = {
    q: '',
    category: '',
    skills: [],
    budget_min: '',
    budget_max: '',
    posted: 'any',
    status: 'open',
    sort: 'newest',
};
export default function Jobs(props: Props) {
    const { url } = usePage();
    return <SearchPage key={url} {...props} />;
}
function SearchPage({
    projects,
    filters,
    categories,
    skills,
    canMatchSkills,
}: Props) {
    const { t, ar } = useTranslation();
    const { auth } = usePage().props;
    const pageUrl = usePage().url;
    const [draft, setDraft] = useState(filters);
    const [drawer, setDrawer] = useState(false);
    const [mobile, setMobile] = useState(false);
    const [busy, setBusy] = useState(false);
    const [skillQuery, setSkillQuery] = useState('');
    useEffect(() => {
        try {
            const stored = sessionStorage.getItem('elancer-search-position');
            if (!stored) return;
            const position = JSON.parse(stored);
            if (position.url !== pageUrl || !Number.isFinite(position.y))
                return;
            const frame = requestAnimationFrame(() => {
                window.scrollTo(0, position.y);
                sessionStorage.removeItem('elancer-search-position');
            });
            return () => cancelAnimationFrame(frame);
        } catch {
            // Storage can be unavailable; browser Back retains its own history.
        }
    }, [pageUrl]);
    useEffect(() => {
        const media = matchMedia('(max-width: 1023px)');
        const update = () => setMobile(media.matches);
        update();
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);
    function apply(next = draft) {
        if (
            next.budget_min &&
            next.budget_max &&
            Number(next.budget_min) > Number(next.budget_max)
        )
            return;
        setBusy(true);
        setDrawer(false);
        router.get('/jobs', { ...next }, { onFinish: () => setBusy(false) });
    }
    const rangeError =
        draft.budget_min &&
        draft.budget_max &&
        Number(draft.budget_min) > Number(draft.budget_max);
    function field(key: keyof Filters, value: string) {
        setDraft((d) => ({ ...d, [key]: value }));
    }
    const controls = (
        <form
            className="job-filters"
            onSubmit={(e) => {
                e.preventDefault();
                apply();
            }}
        >
            <div className="job-filter-heading">
                <h2>{t('Filters')}</h2>
                <button
                    type="button"
                    onClick={() => {
                        setDraft(empty);
                        apply(empty);
                    }}
                >
                    {t('Clear all')}
                </button>
            </div>
            <label htmlFor="job-category">{t('Category')}</label>
            <select
                id="job-category"
                value={draft.category}
                onChange={(e) => field('category', e.target.value)}
            >
                <option value="">{t('All categories')}</option>
                {categories.map((c) => (
                    <option key={c.id} value={c.slug}>
                        {c.categoryname}
                    </option>
                ))}
            </select>
            <fieldset>
                <legend>{t('Skills')}</legend>
                <label className="sr-only" htmlFor="filter-skill-search">
                    {t('Search skills')}
                </label>
                <Input
                    id="filter-skill-search"
                    value={skillQuery}
                    onChange={(e) => setSkillQuery(e.target.value)}
                    placeholder={t('Search skills')}
                />
                <div className="job-skill-options">
                    {skills
                        .filter((s) =>
                            s.name
                                .toLocaleLowerCase()
                                .includes(skillQuery.toLocaleLowerCase()),
                        )
                        .map((s) => (
                            <label key={s.id}>
                                <input
                                    type="checkbox"
                                    checked={draft.skills.includes(s.id)}
                                    disabled={
                                        !draft.skills.includes(s.id) &&
                                        draft.skills.length >= 15
                                    }
                                    onChange={(e) =>
                                        setDraft((d) => ({
                                            ...d,
                                            skills: e.target.checked
                                                ? [...d.skills, s.id]
                                                : d.skills.filter(
                                                      (id) => id !== s.id,
                                                  ),
                                        }))
                                    }
                                />
                                <bdi>{s.name}</bdi>
                            </label>
                        ))}
                </div>
                <small>{t('Projects must include all selected skills.')}</small>
            </fieldset>
            <fieldset>
                <legend>{t('Budget (USD)')}</legend>
                <div className="job-budget-fields">
                    <div>
                        <label htmlFor="budget-min">{t('Minimum')}</label>
                        <Input
                            id="budget-min"
                            type="number"
                            min="1"
                            max="1000000"
                            step={0.01}
                            value={draft.budget_min}
                            onChange={(e) =>
                                field('budget_min', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <label htmlFor="budget-max">{t('Maximum')}</label>
                        <Input
                            id="budget-max"
                            type="number"
                            min="1"
                            max="1000000"
                            step={0.01}
                            value={draft.budget_max}
                            onChange={(e) =>
                                field('budget_max', e.target.value)
                            }
                        />
                    </div>
                </div>
                {rangeError && (
                    <p role="alert">
                        {t(
                            'The maximum budget must be at least the minimum budget.',
                        )}
                    </p>
                )}
            </fieldset>
            <label htmlFor="job-posted">{t('Date posted')}</label>
            <select
                id="job-posted"
                value={draft.posted}
                onChange={(e) => field('posted', e.target.value)}
            >
                {[
                    ['any', 'Any time'],
                    ['1', 'Last 24 hours'],
                    ['7', 'Last 7 days'],
                    ['30', 'Last 30 days'],
                ].map(([value, label]) => (
                    <option key={value} value={value}>
                        {t(label)}
                    </option>
                ))}
            </select>
            <label htmlFor="job-status">{t('Availability')}</label>
            <select
                id="job-status"
                value={draft.status}
                onChange={(e) => field('status', e.target.value)}
            >
                <option value="open">{t('Open for applications')}</option>
                <option value="all">{t('Include closed projects')}</option>
            </select>
            <Button type="submit" disabled={busy || !!rangeError}>
                {t('Apply filters')}
            </Button>
        </form>
    );
    const chips: { label: string; next: Filters }[] = [];
    if (filters.q)
        chips.push({ label: filters.q, next: { ...filters, q: '' } });
    if (filters.category)
        chips.push({
            label:
                categories.find((c) => c.slug === filters.category)
                    ?.categoryname ?? filters.category,
            next: { ...filters, category: '' },
        });
    filters.skills.forEach((id) =>
        chips.push({
            label: skills.find((s) => s.id === id)?.name ?? String(id),
            next: {
                ...filters,
                skills: filters.skills.filter((s) => s !== id),
            },
        }),
    );
    if (filters.budget_min || filters.budget_max)
        chips.push({
            label: `${t('Budget (USD)')}: ${filters.budget_min || '0'} – ${filters.budget_max || '∞'}`,
            next: { ...filters, budget_min: '', budget_max: '' },
        });
    if (filters.posted !== 'any')
        chips.push({
            label: t(
                filters.posted === '1'
                    ? 'Last 24 hours'
                    : filters.posted === '7'
                      ? 'Last 7 days'
                      : 'Last 30 days',
            ),
            next: { ...filters, posted: 'any' },
        });
    if (filters.status === 'all')
        chips.push({
            label: t('Include closed projects'),
            next: { ...filters, status: 'open' },
        });
    return (
        <DiscoveryLayout>
            <Head title={t('Find jobs')} />
            <header className="jobs-heading">
                <div>
                    <span className="jobs-eyebrow">
                        {t('Work worth doing')}
                    </span>
                    <h1>{t('Find your next project')}</h1>
                    <p>
                        {t(
                            'Explore opportunities that fit your skills and the way you work.',
                        )}
                    </p>
                </div>
                <Link
                    className="job-post-link"
                    href={auth.user ? '/my-projects' : '/register'}
                >
                    {t('Post a project')}
                </Link>
            </header>
            <form
                className="jobs-search"
                onSubmit={(e) => {
                    e.preventDefault();
                    apply();
                }}
            >
                <Search size={20} aria-hidden="true" />
                <label className="sr-only" htmlFor="job-search">
                    {t('Search jobs')}
                </label>
                <Input
                    id="job-search"
                    value={draft.q}
                    maxLength={100}
                    placeholder={t('Search by title or keyword')}
                    onChange={(e) => field('q', e.target.value)}
                />
                <Button type="submit" disabled={busy}>
                    {t('Search')}
                </Button>
            </form>
            <div className="job-active-filters">
                {chips.map((chip, index) => (
                    <button
                        key={index}
                        onClick={() => apply(chip.next)}
                        aria-label={t('Remove filter: :name', {
                            name: chip.label,
                        })}
                    >
                        <bdi>{chip.label}</bdi>
                        <X size={14} aria-hidden="true" />
                    </button>
                ))}
            </div>
            <div className="jobs-grid">
                {!mobile && <aside>{controls}</aside>}
                <section aria-busy={busy} aria-label={t('Search results')}>
                    <div className="jobs-results-bar">
                        <h2 aria-live="polite">
                            {t(':count projects', { count: projects.total })}
                        </h2>
                        {mobile && (
                            <Sheet open={drawer} onOpenChange={setDrawer}>
                                <SheetTrigger asChild>
                                    <Button
                                        variant="outline"
                                        startIcon={
                                            <SlidersHorizontal size={16} />
                                        }
                                    >
                                        {t('Filters')}
                                    </Button>
                                </SheetTrigger>
                                <SheetContent
                                    side={ar ? 'right' : 'left'}
                                    aria-describedby={undefined}
                                    className="job-filter-drawer"
                                >
                                    <SheetHeader>
                                        <SheetTitle>
                                            {t('Refine your search')}
                                        </SheetTitle>
                                    </SheetHeader>
                                    {controls}
                                </SheetContent>
                            </Sheet>
                        )}
                        <label className="job-sort">
                            {t('Sort by')}
                            <select
                                value={draft.sort}
                                onChange={(e) =>
                                    apply({ ...draft, sort: e.target.value })
                                }
                            >
                                <option value="newest">{t('Newest')}</option>
                                {canMatchSkills && (
                                    <option value="match">
                                        {t('Best skill match')}
                                    </option>
                                )}
                            </select>
                        </label>
                    </div>
                    {projects.data.length ? (
                        <div className="job-results">
                            {projects.data.map((job) => (
                                <JobCard
                                    key={job.id}
                                    job={job}
                                    search={pageUrl.split('?')[1] ?? ''}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="jobs-empty">
                            <Search size={32} aria-hidden="true" />
                            <h2>{t('No projects found')}</h2>
                            <p>
                                {t(
                                    'Try fewer filters or check back for new opportunities.',
                                )}
                            </p>
                            <Button
                                variant="outline"
                                onClick={() => apply(empty)}
                            >
                                {t('Clear all filters')}
                            </Button>
                        </div>
                    )}
                    <Pagination data={projects} />
                </section>
            </div>
        </DiscoveryLayout>
    );
}
