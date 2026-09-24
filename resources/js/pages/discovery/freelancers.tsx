import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search } from 'lucide-react';
import { DiscoveryLayout } from './shared';
import type { Page, Skill } from './shared';
import FreelancerCard from '@/components/freelancer-card';
import type { Freelancer } from '@/components/freelancer-card';
import Input from '@/components/tailadmin/input';
import Button from '@/components/tailadmin/button';
import Pagination from '@/components/tailadmin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-marketplace.css';
export default function Freelancers({
    freelancers,
    filters,
    skills,
}: {
    freelancers: Page<Freelancer>;
    filters: { q: string; skill: string; availability: string };
    skills: Skill[];
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters);
    return (
        <DiscoveryLayout>
            <Head title={t('Find freelancers')} />
            <header className="jobs-heading">
                <div>
                    <h1>{t('Find your next collaborator')}</h1>
                    <p>
                        {t(
                            'Explore independent talent, their skills and their work.',
                        )}
                    </p>
                </div>
            </header>
            <form
                className="talent-filters"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/freelancers', search, { preserveState: true });
                }}
            >
                <label className="market-field">
                    <span>{t('Search freelancers')}</span>
                    <Input
                        value={search.q}
                        maxLength={100}
                        onChange={(e) =>
                            setSearch({ ...search, q: e.target.value })
                        }
                    />
                </label>
                <label className="market-field">
                    <span>{t('Skill')}</span>
                    <select
                        value={search.skill}
                        onChange={(e) =>
                            setSearch({ ...search, skill: e.target.value })
                        }
                    >
                        <option value="">{t('All skills')}</option>
                        {skills.map((skill) => (
                            <option key={skill.id} value={skill.id}>
                                {skill.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="market-field">
                    <span>{t('Availability')}</span>
                    <select
                        value={search.availability}
                        onChange={(e) =>
                            setSearch({
                                ...search,
                                availability: e.target.value,
                            })
                        }
                    >
                        <option value="">{t('Any availability')}</option>
                        <option value="available">
                            {t('Available for work')}
                        </option>
                        <option value="busy">{t('Busy')}</option>
                    </select>
                </label>
                <Button type="submit" startIcon={<Search size={16} />}>
                    {t('Search')}
                </Button>
            </form>
            <p className="market-results">
                {t(':count freelancers', { count: freelancers.total })}
            </p>
            {freelancers.data.length ? (
                <div className="talent-grid">
                    {freelancers.data.map((person) => (
                        <FreelancerCard key={person.id} person={person} />
                    ))}
                </div>
            ) : (
                <div className="market-empty">
                    <h2>{t('No matching freelancers yet')}</h2>
                    <p>{t('Try another skill or a broader search.')}</p>
                </div>
            )}
            <Pagination data={freelancers} />
        </DiscoveryLayout>
    );
}
