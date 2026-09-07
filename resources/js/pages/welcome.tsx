import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    Check,
    Code2,
    PenTool,
    Sparkles,
} from 'lucide-react';
import ElancerWordmark from '@/components/elancer-wordmark';
import HomeMarketplace from '@/components/home/home-marketplace';
import HomePaths from '@/components/home/home-paths';
import HomeThemeToggle from '@/components/home/home-theme-toggle';
import HoverFooter from '@/components/hover-footer';
import Hero from '@/components/ui/animated-shader-hero';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const destination = auth.user ? dashboard().url : register().url;

    return (
        <>
            <Head title="Good people. Great work.">
                <meta
                    name="description"
                    content="Elancer connects independent talent and ambitious ideas. Find your people and make your next great project happen."
                />
            </Head>
            <div className="elancer-home">
                <a href="#main-content" className="elancer-skip-link">
                    Skip to content
                </a>
                <main id="main-content">
                    <Hero
                        trustBadge={{
                            text: 'A meeting place for talent & possibility',
                        }}
                        headline={{
                            line1: 'Good people.',
                            line2: 'Extraordinary work.',
                        }}
                        subtitle="Big ideas deserve the right people. Connect with independent talent and turn your next what-if into something real."
                        buttons={{
                            primary: {
                                text: auth.user
                                    ? 'Go to your workspace'
                                    : 'Let’s get to work',
                                href: destination,
                            },
                            secondary: {
                                text: 'See how it works',
                                href: '#how-it-works',
                            },
                        }}
                    >
                        <header className="elancer-header">
                            <Link href="/" aria-label="Elancer home">
                                <ElancerWordmark />
                            </Link>
                            <nav
                                className="elancer-main-nav"
                                aria-label="Main navigation"
                            >
                                <a href="#categories">Explore categories</a>
                                <a href="#featured-freelancers">
                                    Meet the talent
                                </a>
                                <a href="#how-it-works">How it works</a>
                            </nav>
                            <div className="elancer-account-nav">
                                <HomeThemeToggle />
                                {!auth.user && (
                                    <Link href={login()}>Log in</Link>
                                )}
                                <Link
                                    href={destination}
                                    className="elancer-nav-cta"
                                >
                                    {auth.user ? 'Workspace' : 'Get started'}{' '}
                                    <ArrowUpRight
                                        size={15}
                                        aria-hidden="true"
                                    />
                                </Link>
                            </div>
                        </header>
                    </Hero>

                    <section
                        id="why-elancer"
                        className="elancer-intro"
                        aria-labelledby="intro-heading"
                    >
                        <div className="elancer-section-topline">
                            <span>
                                <span className="elancer-small-square" /> BUILT
                                AROUND YOU
                            </span>
                            <span>Less friction. More making.</span>
                        </div>
                        <div className="elancer-intro-grid">
                            <div className="elancer-illustration-wrap">
                                <img
                                    src="/images/freelancer-at-work.png"
                                    alt="Illustration of a freelancer in a green sweater working on a laptop at a sunlit desk."
                                    width="1536"
                                    height="1024"
                                    loading="lazy"
                                    decoding="async"
                                    className="elancer-illustration"
                                />
                                <div className="elancer-illustration-note">
                                    <span className="elancer-note-icon">
                                        <Check
                                            size={15}
                                            strokeWidth={3}
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div>
                                        <strong>In your element.</strong>
                                        <span>Doing what you do best.</span>
                                    </div>
                                </div>
                            </div>
                            <div className="elancer-intro-copy">
                                <span className="elancer-eyebrow">
                                    GOOD WORK IS HUMAN.
                                </span>
                                <h2 id="intro-heading">
                                    Make room for
                                    <br />
                                    your next <span>big thing.</span>
                                </h2>
                                <p>
                                    A fresh perspective. A missing skill.
                                    Someone who just gets it. The right
                                    collaboration can change everything.
                                </p>
                                <p>
                                    Whether you’re building a business or a
                                    career on your own terms, there’s a place
                                    for you here.
                                </p>
                                <Link
                                    href={destination}
                                    className="elancer-text-link"
                                >
                                    Find your starting point{' '}
                                    <ArrowUpRight
                                        size={19}
                                        aria-hidden="true"
                                    />
                                </Link>
                                <div
                                    className="elancer-disciplines"
                                    aria-label="Creative disciplines"
                                >
                                    <span>
                                        <Code2 size={15} aria-hidden="true" />{' '}
                                        Development
                                    </span>
                                    <span>
                                        <PenTool size={15} aria-hidden="true" />{' '}
                                        Design
                                    </span>
                                    <span>
                                        <Sparkles
                                            size={15}
                                            aria-hidden="true"
                                        />{' '}
                                        And your next idea
                                    </span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <HomeMarketplace />
                    <HomePaths destination={destination} />

                    <section
                        id="how-it-works"
                        className="elancer-how"
                        aria-labelledby="how-heading"
                    >
                        <div className="elancer-how-header">
                            <div>
                                <span className="elancer-eyebrow">
                                    FROM HELLO TO LET’S GO
                                </span>
                                <h2 id="how-heading">
                                    Great things start simply.
                                </h2>
                            </div>
                            <Link
                                href={destination}
                                className="elancer-text-link"
                            >
                                Start your story{' '}
                                <ArrowRight size={18} aria-hidden="true" />
                            </Link>
                        </div>
                        <ol className="elancer-steps">
                            <li>
                                <span className="elancer-step-number">01</span>
                                <h3>Bring your ambition.</h3>
                                <p>
                                    Start with an idea you want to build, or a
                                    skill you’re ready to share.
                                </p>
                            </li>
                            <li>
                                <span className="elancer-step-number">02</span>
                                <h3>Find your people.</h3>
                                <p>
                                    Connect around a clear brief, a shared
                                    vision, and the right expertise.
                                </p>
                            </li>
                            <li>
                                <span className="elancer-step-number">03</span>
                                <h3>Make something matter.</h3>
                                <p>
                                    Bring your best work to the table. Create
                                    something you’re proud to put your name on.
                                </p>
                            </li>
                        </ol>
                    </section>
                </main>
                <HoverFooter authenticated={Boolean(auth.user)} />
            </div>
        </>
    );
}
