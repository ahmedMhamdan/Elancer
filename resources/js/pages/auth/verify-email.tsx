import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Mail, RefreshCw, ShieldCheck } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import ElancerSiteHeader from '@/components/elancer-site-header';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import '../../../css/elancer-registration.css';
import '../../../css/elancer-verification.css';

function VerificationEnvelope({ replay }: { replay: number }) {
    const reduceMotion = useReducedMotion();

    return (
        <svg
            className="verification-envelope"
            viewBox="0 0 440 350"
            fill="none"
            aria-hidden="true"
        >
            <defs>
                <clipPath id="verification-letter-window">
                    <rect x="130" y="80" width="180" height="174" />
                </clipPath>
            </defs>
            <circle cx="220" cy="173" r="142" fill="var(--el-soft)" />
            <circle
                cx="220"
                cy="173"
                r="166"
                stroke="var(--el-border)"
                strokeDasharray="3 9"
            />
            <ellipse
                cx="220"
                cy="312"
                rx="106"
                ry="9"
                fill="var(--el-border)"
            />
            <g stroke="var(--el-accent)" strokeWidth="2" strokeLinecap="round">
                <path d="M57 131h12m-6-6v12M357 243h12m-6-6v12" />
                <circle cx="356" cy="95" r="5" />
                <circle cx="86" cy="262" r="3" />
            </g>
            <motion.g
                key={replay}
                initial={reduceMotion ? false : { y: 12, rotate: -3 }}
                animate={{ y: 0, rotate: 0 }}
                transition={{ type: 'spring', stiffness: 110, damping: 17 }}
                style={{ transformOrigin: '220px 225px' }}
            >
                <path
                    d="M102 166 208 87a20 20 0 0 1 24 0l106 79v119H102Z"
                    fill="var(--el-accent)"
                />
                <g clipPath="url(#verification-letter-window)">
                    <motion.g
                        initial={reduceMotion ? false : { y: 66 }}
                        animate={{ y: 0 }}
                        transition={{
                            delay: 0.18,
                            type: 'spring',
                            stiffness: 100,
                            damping: 18,
                        }}
                    >
                        <rect
                            x="132"
                            y="100"
                            width="176"
                            height="159"
                            rx="12"
                            fill="var(--el-surface)"
                            stroke="var(--el-border-strong)"
                        />
                        <rect
                            x="154"
                            y="125"
                            width="34"
                            height="34"
                            rx="9"
                            fill="var(--el-soft)"
                        />
                        <path
                            d="M162 136h18v12h-18zm0 0 9 6 9-6"
                            stroke="var(--el-accent)"
                            strokeWidth="1.5"
                            strokeLinejoin="round"
                        />
                        <path
                            d="M202 134h58m-58 12h36M155 179h128m-128 14h100"
                            stroke="var(--el-border-strong)"
                            strokeWidth="5"
                            strokeLinecap="round"
                        />
                    </motion.g>
                </g>
                <path
                    d="M102 166 220 242 338 166v113a14 14 0 0 1-14 14H116a14 14 0 0 1-14-14Z"
                    fill="var(--el-mint-mid)"
                    stroke="var(--el-accent)"
                    strokeWidth="2"
                    strokeLinejoin="round"
                />
                <path
                    d="m105 287 96-73a31 31 0 0 1 38 0l96 73"
                    fill="var(--el-mint)"
                    stroke="var(--el-accent)"
                    strokeWidth="2"
                    strokeLinejoin="round"
                />
                <circle
                    cx="315"
                    cy="270"
                    r="30"
                    fill="var(--el-brand)"
                    stroke="var(--el-surface)"
                    strokeWidth="6"
                />
                <motion.path
                    d="m303 270 8 8 16-17"
                    stroke="var(--el-on-brand)"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    initial={reduceMotion ? false : { pathLength: 0 }}
                    animate={{ pathLength: 1 }}
                    transition={{ delay: 0.65, duration: 0.35 }}
                />
            </motion.g>
        </svg>
    );
}

export default function VerifyEmail({ status }: { status?: string }) {
    const { auth } = usePage().props;
    const [replay, setReplay] = useState(0);
    const sent = status === 'verification-link-sent';

    return (
        <div className="elancer-registration elancer-verification">
            <Head title="Verify your email" />
            <a href="#verification-content" className="registration-skip">
                Skip to email verification
            </a>
            <ElancerSiteHeader registration />

            <main
                id="verification-content"
                className="verification-main"
                tabIndex={-1}
            >
                <section
                    className="verification-copy"
                    aria-labelledby="verification-title"
                >
                    <div
                        className="verification-progress"
                        aria-label="Account created. Email verification is next."
                    >
                        <span>
                            <Check size={14} aria-hidden="true" /> Account
                            created
                        </span>
                        <span
                            className="verification-progress-line"
                            aria-hidden="true"
                        />
                        <span aria-current="step">
                            <Mail size={14} aria-hidden="true" /> Verify email
                        </span>
                    </div>
                    <h1 id="verification-title">
                        Your next chapter.
                        <br />
                        One click away.
                    </h1>
                    <p className="verification-intro">
                        Check your inbox to verify your email and get started on
                        Elancer.
                    </p>

                    <div className="verification-recipient">
                        <Mail size={21} aria-hidden="true" />
                        <div>
                            <p>Verification link sent to</p>
                            <strong>{auth.user.email}</strong>
                        </div>
                    </div>
                    <p className="verification-instructions">
                        Open the email from Elancer and click the verification
                        link. You can close this page once your email is
                        verified.
                    </p>

                    <Form
                        {...send.form()}
                        className="verification-form"
                        onSuccess={() => setReplay((value) => value + 1)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <button
                                    type="submit"
                                    className="registration-submit"
                                    disabled={processing}
                                    aria-busy={processing}
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <RefreshCw
                                            size={17}
                                            aria-hidden="true"
                                        />
                                    )}
                                    {processing
                                        ? 'Sending verification email…'
                                        : 'Resend verification email'}
                                </button>
                                <div
                                    className="verification-feedback"
                                    role="status"
                                    aria-live="polite"
                                    aria-atomic="true"
                                >
                                    {sent && (
                                        <p>
                                            <Check
                                                size={16}
                                                aria-hidden="true"
                                            />{' '}
                                            A fresh link is on its way. Check
                                            your inbox.
                                        </p>
                                    )}
                                </div>
                                {Object.keys(errors).length > 0 && (
                                    <div
                                        className="registration-error-summary"
                                        role="alert"
                                    >
                                        {Object.values(errors).map(
                                            (error, index) => (
                                                <p key={index}>{error}</p>
                                            ),
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                    </Form>

                    <p className="verification-logout">
                        Signed in with the wrong email?{' '}
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => router.flushAll()}
                        >
                            Log out
                        </Link>
                    </p>
                </section>

                <aside
                    className="verification-art"
                    aria-labelledby="verification-art-title"
                >
                    <VerificationEnvelope replay={replay} />
                    <div className="verification-art-caption">
                        <h2 id="verification-art-title">
                            A little check. A lot of possibilities.
                        </h2>
                        <p>
                            Your skills, your ideas, your next opportunity.
                            <br />
                            It all starts with a verified email.
                        </p>
                    </div>
                    <div className="verification-help">
                        <ShieldCheck size={22} aria-hidden="true" />
                        <div>
                            <h3>Can’t find the email?</h3>
                            <p>
                                Check your spam or junk folder. If it’s not
                                there, wait a minute and request a fresh link.
                            </p>
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    );
}
