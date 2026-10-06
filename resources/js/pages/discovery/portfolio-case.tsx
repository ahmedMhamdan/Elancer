import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck } from 'lucide-react';
import { CaseBody, type CaseContent } from '@/components/portfolio-case';
import ReportDialog from '@/components/report-dialog';
import { useTranslation } from '@/hooks/use-translation';
import { DiscoveryLayout } from './shared';
import '../../../css/elancer-marketplace.css';

export type PublicCase = {
    id: number;
    content: CaseContent;
    published_at: string;
    elancer_work: boolean;
};

export default function PortfolioCase({
    case: item,
    freelancer,
    canReport,
}: {
    case: PublicCase;
    canReport: boolean;
    freelancer: { id: number; name: string; headline: string | null };
}) {
    const { t } = useTranslation();
    return (
        <DiscoveryLayout>
            <Head title={item.content.title} />
            <Link className="job-back" href={`/freelancers/${freelancer.id}`}>
                <ArrowLeft size={17} className="rtl:rotate-180" />
                <bdi>{freelancer.name}</bdi>
            </Link>
            <section className="talent-profile-header">
                <div>
                    <span className="talent-availability">
                        {t('Case study')}
                    </span>
                    <h1 dir="auto">{item.content.title}</h1>
                    {item.elancer_work && (
                        <p className="talent-meta">
                            <BadgeCheck size={16} aria-hidden="true" />
                            {t(
                                'Completed on Elancer and published with the approval of the client.',
                            )}
                        </p>
                    )}
                </div>
            </section>
            <div className="market-columns">
                <section className="market-panel">
                    <CaseBody content={item.content} />
                </section>
                <aside className="market-stack">
                    <section className="market-panel">
                        <h2>{t('Freelancer')}</h2>
                        <p dir="auto">
                            <strong>{freelancer.name}</strong>
                        </p>
                        <p dir="auto" className="market-muted">
                            {freelancer.headline}
                        </p>
                        <Link
                            className="talent-profile-link"
                            href={`/freelancers/${freelancer.id}`}
                        >
                            {t('View profile')}
                        </Link>
                        {canReport && (
                            <ReportDialog
                                type="case"
                                id={item.id}
                                label={t('Report this case study')}
                            />
                        )}
                    </section>
                </aside>
            </div>
        </DiscoveryLayout>
    );
}
