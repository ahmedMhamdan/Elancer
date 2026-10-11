// Rename form follows local TailAdmin components/form/form-elements/DefaultInputs.tsx.
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import type { Page } from '@/pages/discovery/shared';
import { SavedLayout } from './shared';

type SavedSearch = {
    id: number;
    name: string;
    // False when a stored category or skill no longer exists; the search is then not a link.
    available: boolean;
    href: string | null;
    filters: {
        q: string;
        category: string | null;
        skills: string[];
        skill_mode: string;
        budget_min: string;
        budget_max: string;
        posted: string;
        proposals: string;
        status: string;
        sort: string;
    };
};

export default function SavedSearches({
    searches,
}: {
    searches: Page<SavedSearch>;
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<number | null>(null);
    const [name, setName] = useState('');
    const [error, setError] = useState('');
    // The same wording the search page uses for its own filter chips.
    function summary({ filters }: SavedSearch): string[] {
        const parts: string[] = [];
        if (filters.q) parts.push(filters.q);
        if (filters.category) parts.push(filters.category);
        parts.push(...filters.skills);
        if (filters.skill_mode === 'all' && filters.skills.length > 1)
            parts.push(t('Requires all selected skills'));
        if (filters.budget_min || filters.budget_max)
            parts.push(
                `${t('Budget (USD)')}: ${filters.budget_min || '0'} – ${filters.budget_max || '∞'}`,
            );
        if (filters.posted !== 'any')
            parts.push(
                t(
                    filters.posted === '1'
                        ? 'Last 24 hours'
                        : filters.posted === '7'
                          ? 'Last 7 days'
                          : 'Last 30 days',
                ),
            );
        if (filters.proposals !== 'any')
            parts.push(
                `${t('Proposals received')}: ${t(
                    filters.proposals === '0-4'
                        ? 'Fewer than 5'
                        : filters.proposals === '5-9'
                          ? '5 to 9'
                          : filters.proposals === '10-19'
                            ? '10 to 19'
                            : '20 or more',
                )}`,
            );
        if (filters.status === 'all') parts.push(t('Include closed projects'));
        if (filters.sort === 'match') parts.push(t('Best skill match'));
        return parts.length ? parts : [t('All open projects')];
    }
    function rename(id: number) {
        router.patch(
            `/saved/searches/${id}`,
            { name },
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.name ?? ''),
                onSuccess: () => setEditing(null),
            },
        );
    }
    return (
        <SavedLayout
            title={t('Saved searches')}
            intro={t(
                'Named sets of job filters. Opening one always shows current results.',
            )}
        >
            {!searches.data.length && (
                <section className="market-panel">
                    <h3>{t('No saved searches yet.')}</h3>
                    <p className="market-muted">
                        {t(
                            'Set your filters on Find jobs, then choose Save this search.',
                        )}
                    </p>
                    <Link href="/jobs">{t('Find jobs')}</Link>
                </section>
            )}
            {searches.data.map((search) => (
                <article key={search.id} className="market-panel market-stack">
                    {editing === search.id ? (
                        <form
                            className="grid gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                rename(search.id);
                            }}
                        >
                            <Label htmlFor={`saved-search-${search.id}`}>
                                {t('Search name')}
                            </Label>
                            <Input
                                id={`saved-search-${search.id}`}
                                value={name}
                                maxLength={80}
                                required
                                aria-invalid={!!error}
                                aria-describedby={`saved-search-error-${search.id}`}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                            />
                            <p
                                id={`saved-search-error-${search.id}`}
                                role="alert"
                                className="text-destructive text-sm"
                            >
                                {error}
                            </p>
                            <div className="market-actions">
                                <Button type="submit">{t('Save')}</Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(null)}
                                >
                                    {t('Cancel')}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <h3 dir="auto">{search.name}</h3>
                    )}
                    <div className="job-skills">
                        {summary(search).map((part, index) => (
                            <span key={index} dir="auto">
                                {part}
                            </span>
                        ))}
                    </div>
                    {!search.available && (
                        <p role="status">
                            {t(
                                'A filter in this search is no longer available.',
                            )}
                        </p>
                    )}
                    <div className="market-actions">
                        {search.href && (
                            <Link
                                href={search.href}
                                aria-label={t('Open :name', {
                                    name: search.name,
                                })}
                            >
                                {t('Open')}
                            </Link>
                        )}
                        {editing !== search.id && (
                            <Button
                                variant="outline"
                                aria-label={t('Rename :name', {
                                    name: search.name,
                                })}
                                onClick={() => {
                                    setName(search.name);
                                    setError('');
                                    setEditing(search.id);
                                }}
                            >
                                {t('Rename')}
                            </Button>
                        )}
                        <Button
                            variant="danger-outline"
                            aria-label={t('Delete :name', {
                                name: search.name,
                            })}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        t('Delete this saved search?'),
                                    )
                                )
                                    router.delete(
                                        `/saved/searches/${search.id}`,
                                        { preserveScroll: true },
                                    );
                            }}
                        >
                            {t('Delete')}
                        </Button>
                    </div>
                </article>
            ))}
            <Pagination data={searches} />
        </SavedLayout>
    );
}
