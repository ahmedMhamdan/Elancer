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
    const destination = authenticated ? dashboard().url : register().url;

    return (
        <footer className="elancer-hover-footer">
            <FooterBackgroundGradient />
            <div className="elancer-footer-content">
                <div className="elancer-footer-grid">
                    <div className="elancer-footer-brand">
                        <Link href="/" aria-label="Elancer home">
                            <ElancerWordmark />
                        </Link>
                        <p>
                            A meeting place for independent talent, ambitious
                            ideas, and the people who bring them to life.
                        </p>
                        <span className="elancer-footer-tagline">
                            <span /> Made for people who make things.
                        </span>
                    </div>
                    <nav aria-label="Explore Elancer">
                        <h2>Explore</h2>
                        <ul>
                            <li>
                                <a href="#categories">Explore categories</a>
                            </li>
                            <li>
                                <a href="#featured-freelancers">
                                    Sample talent
                                </a>
                            </li>
                            <li>
                                <a href="#main-content">
                                    Back to the beginning
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <nav aria-label="Your account">
                        <h2>Your next step</h2>
                        <ul>
                            {authenticated ? (
                                <li>
                                    <Link href={dashboard()}>
                                        Your workspace
                                    </Link>
                                </li>
                            ) : (
                                <>
                                    <li>
                                        <Link href={register()}>
                                            Create an account
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href={login()}>Log in</Link>
                                    </li>
                                </>
                            )}
                            <li>
                                <a href="#why-elancer">
                                    Find your starting point
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div className="elancer-footer-invitation">
                        <h2>
                            A little ambition.
                            <br />A lot of possibility.
                        </h2>
                        <p>
                            Your next great collaboration starts with a hello.
                        </p>
                        <Link href={destination}>
                            Let’s get to work{' '}
                            <ArrowUpRight size={17} aria-hidden="true" />
                        </Link>
                    </div>
                </div>
                <div className="elancer-footer-meta">
                    <p>
                        © {new Date().getFullYear()} Elancer. All rights
                        reserved.
                    </p>
                    <a href="#main-content">
                        Back to top <ArrowUp size={14} aria-hidden="true" />
                    </a>
                </div>
            </div>
            <div className="elancer-footer-art">
                <TextHoverEffect text="Elancer" duration={0.2} />
            </div>
        </footer>
    );
}
