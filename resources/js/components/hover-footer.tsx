import { useTranslation } from '@/hooks/use-translation';
import { Link } from '@inertiajs/react';
import { ArrowUp, ArrowUpRight } from 'lucide-react';
import ElancerWordmark from '@/components/elancer-wordmark';
import {
    FooterBackgroundGradient,
    TextHoverEffect,
} from '@/components/ui/hover-footer';
import { dashboard, login, register } from '@/routes';

export default function HoverFooter({
    authenticated = false,
}: {
    authenticated?: boolean;
}) {
    const { t } = useTranslation();

    const destination = authenticated ? dashboard().url : register().url;

    return (
        <footer className="elancer-hover-footer">
            <FooterBackgroundGradient />
            <div className="elancer-footer-content">
                <div className="elancer-footer-grid">
                    <div className="elancer-footer-brand">
                        <Link href="/" aria-label={t('Elancer home')}>
                            <ElancerWordmark />
                        </Link>
                        <p>
                            {t(
                                'A meeting place for independent talent, ambitious ideas, and the people who bring them to life.',
                            )}
                        </p>
                        <span className="elancer-footer-tagline">
                            <span /> {t('Made for people who make things.')}
                        </span>
                    </div>
                    <nav aria-label={t('Explore Elancer')}>
                        <h2>{t('Explore')}</h2>
                        <ul>
                            <li>
                                <a href="#categories">
                                    {t('Explore categories')}
                                </a>
                            </li>
                            <li>
                                <a href="#featured-freelancers">
                                    {t('Sample talent')}
                                </a>
                            </li>
                            <li>
                                <a href="#main-content">
                                    {t('Back to the beginning')}
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <nav aria-label={t('Your account')}>
                        <h2>{t('Your next step')}</h2>
                        <ul>
                            {authenticated ? (
                                <li>
                                    <Link href={dashboard()}>
                                        {t('Your workspace')}
                                    </Link>
                                </li>
                            ) : (
                                <>
                                    <li>
                                        <Link href={register()}>
                                            {t('Create an account')}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href={login()}>
                                            {t('Log in')}
                                        </Link>
                                    </li>
                                </>
                            )}
                            <li>
                                <a href="#why-elancer">
                                    {t('Find your starting point')}
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div className="elancer-footer-invitation">
                        <h2>
                            {t('A little ambition.')}
                            <br />
                            {t('A lot of possibility.')}
                        </h2>
                        <p>
                            {t(
                                'Your next great collaboration starts with a hello.',
                            )}
                        </p>
                        <Link href={destination}>
                            {t('Let’s get to work')}{' '}
                            <ArrowUpRight size={17} aria-hidden="true" />
                        </Link>
                    </div>
                </div>
                <div className="elancer-footer-meta">
                    <p>
                        © {new Date().getFullYear()}{' '}
                        {t('Elancer. All rights reserved.')}
                    </p>
                    <a href="#main-content">
                        {t('Back to top')}
                        <ArrowUp size={14} aria-hidden="true" />
                    </a>
                </div>
            </div>
            <div className="elancer-footer-art">
                <TextHoverEffect text="Elancer" duration={0.2} />
            </div>
        </footer>
    );
}
