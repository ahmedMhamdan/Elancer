import { router, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    LayoutGrid,
    Search,
    SlidersHorizontal,
    UserRound,
} from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { AppleSpotlight } from '@/components/ui/apple-spotlight';
import { useTranslation } from '@/hooks/use-translation';

type SearchKind = 'jobs' | 'freelancers' | 'categories';
type Options = {
    categories: { id: number; slug: string; categoryname: string }[];
    skills: { id: number; name: string }[];
};
const empty = {
    category: '',
    skill: '',
    budget_min: '',
    budget_max: '',
    posted: 'any',
    status: 'open',
    availability: '',
};

export default function ElancerSpotlight() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState<SearchKind>(
        auth.user?.workspace_role === 'client' ? 'freelancers' : 'jobs',
    );
    const [query, setQuery] = useState('');
    const [filters, setFilters] = useState(empty);
    const [showFilters, setShowFilters] = useState(false);
    const [options, setOptions] = useState<Options | null>(null);
    const [optionError, setOptionError] = useState(false);
    const [retry, setRetry] = useState(0);
    const [busy, setBusy] = useState(false);
    const trigger = useRef<HTMLButtonElement>(null);
    const id = useId();
    const rangeError =
        kind === 'jobs' &&
        filters.budget_min !== '' &&
        filters.budget_max !== '' &&
        Number(filters.budget_max) < Number(filters.budget_min);

    // Keyboard entry adapted from TailAdmin src/layout/AppHeader.tsx.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'k' &&
                trigger.current?.getClientRects().length
            ) {
                event.preventDefault();
                trigger.current.click();
            }
        };
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);
    useEffect(() => {
        if (!open || !showFilters || kind === 'categories' || options) return;
        const controller = new AbortController();
        fetch('/search/filters', {
            signal: controller.signal,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => {
                if (!response.ok) throw new Error('filters');
                return response.json() as Promise<Options>;
            })
            .then((data) => {
                if (!controller.signal.aborted) {
                    setOptions(data);
                    setOptionError(false);
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) setOptionError(true);
            });
        return () => controller.abort();
    }, [open, showFilters, kind, options, retry]);

    function changeOpen(next: boolean) {
        if (next) {
            setKind(
                auth.user?.workspace_role === 'client' ? 'freelancers' : 'jobs',
            );
            setQuery('');
            setFilters(empty);
            setShowFilters(false);
            setOptionError(false);
        }
        setOpen(next);
    }
    function selectMode(next: string) {
        setKind(next as SearchKind);
        setQuery('');
        setFilters(empty);
        setOptionError(false);
    }
    function submit() {
        if (rangeError || busy) {
            setShowFilters(true);
            return;
        }
        const data: Record<string, string | string[]> = {};
        if (query.trim()) data.q = query.trim();
        if (kind === 'jobs') {
            for (const key of [
                'category',
                'budget_min',
                'budget_max',
                'posted',
                'status',
            ] as const) {
                if (filters[key] !== '') data[key] = filters[key];
            }
            if (filters.skill) data.skills = [filters.skill];
        } else if (kind === 'freelancers') {
            if (filters.skill) data.skill = filters.skill;
            if (filters.availability) data.availability = filters.availability;
        }
        setBusy(true);
        router.get('/' + kind, data, {
            onSuccess: () => setOpen(false),
            onFinish: () => setBusy(false),
        });
    }
    const update = (key: keyof typeof empty, value: string) =>
        setFilters((current) => ({ ...current, [key]: value }));
    const modes = [
        {
            id: 'jobs',
            label: t('Jobs'),
            icon: <BriefcaseBusiness size={20} />,
            placeholder: t('Search jobs by title or keyword'),
            description: t(
                'For freelancers: find projects that fit your skills.',
            ),
            submitLabel: t('Search jobs'),
        },
        {
            id: 'freelancers',
            label: t('Freelancers'),
            icon: <UserRound size={20} />,
            placeholder: t('Search freelancers by name or expertise'),
            description: t(
                'For business owners: find the right person for your project.',
            ),
            submitLabel: t('Search freelancers'),
        },
        {
            id: 'categories',
            label: t('Categories'),
            icon: <LayoutGrid size={20} />,
            placeholder: t('Search categories by name'),
            description: t(
                'Explore fields of work and the projects in each category.',
            ),
            submitLabel: t('Search categories'),
        },
    ];
    return (
        <AppleSpotlight
            isOpen={open}
            onOpenChange={changeOpen}
            modes={modes}
            selected={kind}
            onSelect={selectMode}
            query={query}
            onQueryChange={setQuery}
            onSubmit={submit}
            busy={busy}
            trigger={
                <button
                    ref={trigger}
                    type="button"
                    className="site-theme-toggle"
                    aria-label={t('Search Elancer')}
                    title={t('Search Elancer')}
                    aria-keyshortcuts="Control+k Meta+k"
                >
                    <Search size={20} aria-hidden="true" />
                </button>
            }
        >
            {kind !== 'categories' && (
                <div
                    className="elancer-search-filter-area"
                    onInvalidCapture={() => setShowFilters(true)}
                >
                    <button
                        type="button"
                        className="elancer-search-filter-toggle"
                        aria-expanded={showFilters}
                        aria-controls={id + '-filters'}
                        onClick={() => setShowFilters((current) => !current)}
                    >
                        <SlidersHorizontal size={17} aria-hidden="true" />
                        {t('Filters')}
                    </button>
                    <div id={id + '-filters'} hidden={!showFilters}>
                        {/* Select layout adapts TailAdmin form-elements/SelectInputs.tsx. */}
                        <div className="elancer-search-filters">
                            {optionError && (
                                <p
                                    className="elancer-search-options-status"
                                    role="alert"
                                >
                                    {t('Could not load search filters.')}{' '}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setOptionError(false);
                                            setRetry((current) => current + 1);
                                        }}
                                    >
                                        {t('Try again')}
                                    </button>
                                </p>
                            )}
                            {!options && !optionError && (
                                <p
                                    className="elancer-search-options-status"
                                    role="status"
                                >
                                    {t('Loading filters…')}
                                </p>
                            )}
                            {kind === 'jobs' && (
                                <label>
                                    <span>{t('Category')}</span>
                                    <select
                                        disabled={!options}
                                        value={filters.category}
                                        onChange={(event) =>
                                            update(
                                                'category',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('All categories')}
                                        </option>
                                        {options?.categories.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.slug}
                                            >
                                                {category.categoryname}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            <label>
                                <span>{t('Skill')}</span>
                                <select
                                    disabled={!options}
                                    value={filters.skill}
                                    onChange={(event) =>
                                        update('skill', event.target.value)
                                    }
                                >
                                    <option value="">{t('All skills')}</option>
                                    {options?.skills.map((skill) => (
                                        <option key={skill.id} value={skill.id}>
                                            {skill.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            {kind === 'jobs' ? (
                                <>
                                    <label>
                                        <span>{t('Minimum budget (USD)')}</span>
                                        <input
                                            type="number"
                                            min="1"
                                            max="1000000"
                                            step="0.01"
                                            value={filters.budget_min}
                                            onChange={(event) =>
                                                update(
                                                    'budget_min',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    <label>
                                        <span>{t('Maximum budget (USD)')}</span>
                                        <input
                                            type="number"
                                            min="1"
                                            max="1000000"
                                            step="0.01"
                                            value={filters.budget_max}
                                            aria-invalid={
                                                rangeError || undefined
                                            }
                                            aria-describedby={
                                                rangeError
                                                    ? id + '-range-error'
                                                    : undefined
                                            }
                                            onChange={(event) =>
                                                update(
                                                    'budget_max',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    <label>
                                        <span>{t('Date posted')}</span>
                                        <select
                                            value={filters.posted}
                                            onChange={(event) =>
                                                update(
                                                    'posted',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="any">
                                                {t('Any time')}
                                            </option>
                                            <option value="1">
                                                {t('Last 24 hours')}
                                            </option>
                                            <option value="7">
                                                {t('Last 7 days')}
                                            </option>
                                            <option value="30">
                                                {t('Last 30 days')}
                                            </option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>{t('Availability')}</span>
                                        <select
                                            value={filters.status}
                                            onChange={(event) =>
                                                update(
                                                    'status',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="open">
                                                {t('Open for applications')}
                                            </option>
                                            <option value="all">
                                                {t('Include closed projects')}
                                            </option>
                                        </select>
                                    </label>
                                </>
                            ) : (
                                <label>
                                    <span>{t('Availability')}</span>
                                    <select
                                        value={filters.availability}
                                        onChange={(event) =>
                                            update(
                                                'availability',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('Any availability')}
                                        </option>
                                        <option value="available">
                                            {t('Available for work')}
                                        </option>
                                        <option value="busy">
                                            {t('Busy')}
                                        </option>
                                    </select>
                                </label>
                            )}
                        </div>
                        <button
                            type="button"
                            className="elancer-search-clear"
                            onClick={() => setFilters(empty)}
                        >
                            {t('Clear filters')}
                        </button>
                    </div>
                    {rangeError && (
                        <p
                            id={id + '-range-error'}
                            role="alert"
                            className="elancer-search-error"
                        >
                            {t(
                                'The maximum budget must be at least the minimum budget.',
                            )}
                        </p>
                    )}
                </div>
            )}
        </AppleSpotlight>
    );
}
