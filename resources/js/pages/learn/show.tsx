import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import ElancerSiteHeader from '@/components/elancer-site-header';
import HoverFooter from '@/components/hover-footer';
import { publicGuides } from '@/data/public-guides';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-guides.css';

export default function GuidePage({ guide }: { guide: string }) {
    const { t } = useTranslation();
    const guides = publicGuides(t);
    // Only the five explicitly registered public routes can render this page.
    const current = guides.find((item) => item.id === guide)!;
    return (
        <>
            <Head title={current.title}>
                <meta name="description" content={current.intro} />
            </Head>
            <div className="elancer-home elancer-guides">
                <a href="#main-content" className="elancer-skip-link">
                    {t('Skip to content')}
                </a>
                <ElancerSiteHeader />
                <main id="main-content" className="elancer-guide-main">
                    <header className="elancer-guide-heading">
                        <Link href="/" className="elancer-guide-eyebrow">
                            {t('Elancer home')}
                        </Link>
                        <h1>{current.title}</h1>
                        <p>{current.intro}</p>
                    </header>
                    <div className="elancer-guide-layout">
                        <aside className="elancer-guide-aside">
                            <nav aria-label={t('On this page')}>
                                <h2>{t('On this page')}</h2>
                                {current.sections.map((section, index) => (
                                    <a href={'#' + section.id} key={section.id}>
                                        <span aria-hidden="true">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        {section.title}
                                    </a>
                                ))}
                            </nav>
                        </aside>
                        <div className="elancer-guide-articles">
                            {current.sections.map((section, index) => (
                                <section
                                    id={section.id}
                                    key={section.id}
                                    aria-labelledby={section.id + '-title'}
                                >
                                    <div className="elancer-guide-section-label">
                                        <span aria-hidden="true">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        {section.example && (
                                            <span>
                                                {t('Illustrative project idea')}
                                            </span>
                                        )}
                                    </div>
                                    <h2 id={section.id + '-title'}>
                                        {section.title}
                                    </h2>
                                    <p className="elancer-guide-summary">
                                        {section.description}
                                    </p>
                                    <ul>
                                        {section.points.map((point) => (
                                            <li key={point}>{point}</li>
                                        ))}
                                    </ul>
                                    {section.href && (
                                        <Link
                                            href={section.href}
                                            className="elancer-guide-action"
                                        >
                                            {section.action}
                                            <ArrowUpRight
                                                size={18}
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    )}
                                </section>
                            ))}
                        </div>
                    </div>
                    <nav
                        className="elancer-guide-related"
                        aria-label={t('Explore another guide')}
                    >
                        <h2>{t('Explore another guide')}</h2>
                        <div>
                            {guides
                                .filter((item) => item.id !== guide)
                                .map((item) => (
                                    <Link
                                        href={'/learn/' + item.id}
                                        key={item.id}
                                    >
                                        {item.title}
                                        <ArrowUpRight
                                            size={18}
                                            aria-hidden="true"
                                        />
                                    </Link>
                                ))}
                        </div>
                    </nav>
                </main>
                <HoverFooter />
            </div>
        </>
    );
}
