import { useTranslation } from '@/hooks/use-translation';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BriefcaseBusiness, Check, Sparkles } from 'lucide-react';

export default function HomePaths({ destination }: { destination: string }) {
    const { t } = useTranslation();

    return (
        <section
            id="your-next-step"
            className="elancer-paths-section"
            aria-labelledby="paths-heading"
        >
            <div className="elancer-section-heading">
                <div>
                    <span className="elancer-eyebrow">
                        {t('One account, two ways to work')}
                    </span>
                    <h2 id="paths-heading">
                        {t('Bring a project. Share your skills.')}
                    </h2>
                </div>
                <p>
                    {t('Find a collaborator. Find your next chapter.')}
                    <br />
                    {t('Make your own way forward.')}
                </p>
            </div>
            <div className="elancer-paths-grid">
                <article className="elancer-path-card">
                    <div className="elancer-path-top">
                        <span>{t('For clients')}</span>
                        <BriefcaseBusiness
                            size={25}
                            strokeWidth={1.5}
                            aria-hidden="true"
                        />
                    </div>
                    <h3>
                        {t('You have the vision.')}
                        <br />
                        {t('Find your people.')}
                    </h3>
                    <p>
                        {t(
                            'Bring a fresh idea, a tricky challenge, or the next step for your business. Start with the right collaborator.',
                        )}
                    </p>
                    <ul>
                        <li>
                            <Check size={16} aria-hidden="true" />{' '}
                            {t('Shape a clear project brief')}
                        </li>
                        <li>
                            <Check size={16} aria-hidden="true" />{' '}
                            {t('Explore complementary skills')}
                        </li>
                        <li>
                            <Check size={16} aria-hidden="true" />{' '}
                            {t('Build a shared direction')}
                        </li>
                    </ul>
                    <Link href={destination} className="elancer-solid-button">
                        {t('Start as a client')}{' '}
                        <ArrowUpRight size={17} aria-hidden="true" />
                    </Link>
                    <span
                        className="elancer-path-decoration"
                        aria-hidden="true"
                    >
                        ↗
                    </span>
                </article>
                <article className="elancer-path-card elancer-path-independent">
                    <div className="elancer-path-top">
                        <span>{t('For freelancers')}</span>
                        <Sparkles
                            size={25}
                            strokeWidth={1.5}
                            aria-hidden="true"
                        />
                    </div>
                    <h3>
                        {t('Your skills.')}
                        <br />
                        {t('A bigger stage.')}
                    </h3>
                    <p>
                        {t(
                            'Do more of the work you love. Give your experience a home and your next great collaboration a place to begin.',
                        )}
                    </p>
                    <ul>
                        <li>
                            <Check size={16} aria-hidden="true" />{' '}
                            {t('Tell your professional story')}
                        </li>
                        <li>
                            <Check size={16} aria-hidden="true" />{' '}
                            {t('Put your best work forward')}
                        </li>
                        <li>
                            <Check size={16} aria-hidden="true" />{' '}
                            {t('Connect around meaningful ideas')}
                        </li>
                    </ul>
                    <Link href={destination} className="elancer-solid-button">
                        {t('Start as a freelancer')}{' '}
                        <ArrowUpRight size={17} aria-hidden="true" />
                    </Link>
                    <span
                        className="elancer-path-decoration"
                        aria-hidden="true"
                    >
                        ✳
                    </span>
                </article>
            </div>
            <p className="elancer-paths-note">
                {t(
                    'One account, both possibilities. You can be a client and a freelancer.',
                )}
            </p>
        </section>
    );
}
