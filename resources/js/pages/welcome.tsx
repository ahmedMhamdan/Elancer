import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    Check,
    Code2,
    PenTool,
    Sparkles,
} from 'lucide-react';
import ElancerSiteHeader from '@/components/elancer-site-header';
import HomeMarketplace from '@/components/home/home-marketplace';
import HomePaths from '@/components/home/home-paths';
import HoverFooter from '@/components/hover-footer';
import Hero from '@/components/ui/animated-shader-hero';
import { dashboard, register } from '@/routes';

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
            <div className="elancer-home elancer-home-refined">
                <a href="#main-content" className="elancer-skip-link">
                    Skip to content
                </a>
                <ElancerSiteHeader />
                <main id="main-content">
                    <Hero
                        headline={{
                            line1: 'Good people.',
                            line2: 'Extraordinary work.',
                        }}
                        subtitle="Discover designers, developers, and independent specialists. Create a profile to share your skills and introduce your next idea."
                        buttons={{
                            primary: {
                                text: auth.user
                                    ? 'Go to your workspace'
                                    : 'Create your account',
                                href: destination,
                            },
                            secondary: {
                                text: 'Explore skills',
                                href: '#categories',
                            },
                        }}
                    />

                    <section
                        id="why-elancer"
                        className="elancer-intro"
                        aria-labelledby="intro-heading"
                    >
                        <div className="elancer-section-topline">
                            <span>
                                <span
                                    className="elancer-small-square"
                                    aria-hidden="true"
                                />{' '}
                                Work on your terms
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
                                    Built around people
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
                                    Getting started
                                </span>
                                <h2 id="how-heading">
                                    Your first steps on Elancer.
                                </h2>
                            </div>
                            <Link
                                href={destination}
                                className="elancer-text-link"
                            >
                                Get started{' '}
                                <ArrowRight size={18} aria-hidden="true" />
                            </Link>
                        </div>
                        <ol className="elancer-steps">
                            <li>
                                <span className="elancer-step-number">01</span>
                                <h3>Create an account.</h3>
                                <p>
                                    Add your name and email, then choose a
                                    password to create your account.
                                </p>
                            </li>
                            <li>
                                <span className="elancer-step-number">02</span>
                                <h3>Verify your email.</h3>
                                <p>
                                    Open the verification email and follow the
                                    link to access your workspace.
                                </p>
                            </li>
                            <li>
                                <span className="elancer-step-number">03</span>
                                <h3>Introduce yourself.</h3>
                                <p>
                                    Add a headline, a short bio, and your
                                    location to your profile.
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
