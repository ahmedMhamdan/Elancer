import { Head, Link, router } from '@inertiajs/react';
import { Search, ArrowUpRight, Layers } from 'lucide-react';
import { useState } from 'react';
import Button from '@/components/tailadmin/button';
import Input from '@/components/tailadmin/input';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { DiscoveryLayout } from './shared';
import type { Category, Page } from './shared';

export default function Categories({
    categories,
    q,
}: {
    categories: Page<Category>;
    q: string;
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(q);
    return (
        <DiscoveryLayout>
            <Head title={t('Browse job categories')} />
            <header className="jobs-heading">
                <div>
                    <span className="jobs-eyebrow">
                        {t('Find your next opportunity')}
                    </span>
                    <h1>{t('Browse job categories')}</h1>
                    <p>
                        {t(
                            'Explore work by category and find projects that fit your skills.',
                        )}
                    </p>
                </div>
            </header>
            <form
                className="jobs-search"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/categories', search ? { q: search } : {});
                }}
            >
                <label className="sr-only" htmlFor="category-search">
                    {t('Search categories')}
                </label>
                <Search aria-hidden="true" size={20} />
                <Input
                    id="category-search"
                    value={search}
                    maxLength={100}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('Search categories')}
                />
                <Button type="submit">{t('Search')}</Button>
            </form>
            <div className="jobs-section-bar">
                <p>{t(':count categories', { count: categories.total })}</p>
                <Link href="/jobs">
                    {t('View all jobs')} <ArrowUpRight size={16} />
                </Link>
            </div>
            <div className="job-category-grid">
                {categories.data.map((category) => (
                    <Link
                        className="job-category-card"
                        key={category.id}
                        href={`/jobs?category=${encodeURIComponent(category.slug)}`}
                    >
                        <Layers size={24} aria-hidden="true" />
                        <h2 dir="auto">{category.categoryname}</h2>
                        <p>
                            {t(':count open projects', {
                                count: category.open_projects_count ?? 0,
                            })}
                        </p>
                        <ArrowUpRight
                            className="job-category-arrow"
                            size={22}
                            aria-hidden="true"
                        />
                    </Link>
                ))}
            </div>
            {!categories.data.length && (
                <div className="jobs-empty">
                    <h2>{t('No categories found')}</h2>
                    <p>
                        {t(
                            'Try a different search or check back for new categories.',
                        )}
                    </p>
                    <Link href="/categories">{t('Clear search')}</Link>
                </div>
            )}
            <Pagination data={categories} />
        </DiscoveryLayout>
    );
}
