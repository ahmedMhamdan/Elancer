// Plan 033: where an approval form lands when it no longer matches the session (bootstrap/app.php).
// Passport keeps one approval waiting per session, so a page opened again elsewhere, or a session
// that ended, makes the older form stale. Nothing was connected; the app starts a fresh approval.
// Same focused shell as the approval page (oauth/authorize.tsx).
import { Head, Link, usePage } from '@inertiajs/react';
import { Clock, RotateCcw, SquareMousePointer } from 'lucide-react';
import ElancerWordmark from '@/components/elancer-wordmark';
import LanguageToggle from '@/components/language-toggle';
import ThemeToggle from '@/components/theme-toggle';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-two-factor.css';
import '../../../css/elancer-connect.css';

export default function ApprovalExpired() {
    const { t } = useTranslation();
    const signedIn = Boolean(usePage().props.auth.user);

    return (
        <div className="elancer-two-factor">
            <Head title={t('This approval page is out of date')} />
            <header className="two-factor-header">
                <Link
                    href="/"
                    aria-label={t('Elancer home')}
                    className="two-factor-brand"
                >
                    <ElancerWordmark />
                </Link>
                <div className="flex items-center gap-2">
                    <LanguageToggle />
                    <ThemeToggle />
                </div>
            </header>
            <main className="two-factor-main connect-main">
                <div className="two-factor-intro">
                    <span className="connect-badge" aria-hidden="true">
                        <Clock />
                    </span>
                    <h1>{t('This approval page is out of date')}</h1>
                    <p>
                        {t(
                            'Nothing was connected. A newer approval page was opened, or this one waited too long.',
                        )}
                    </p>
                </div>

                <section
                    className="connect-card"
                    aria-labelledby="connect-next"
                >
                    <h2 id="connect-next">{t('To connect the app')}</h2>
                    <ul>
                        <li>
                            <RotateCcw aria-hidden="true" />
                            <span>
                                {t(
                                    'Go back to your AI app and connect again. It opens a new approval page.',
                                )}
                            </span>
                        </li>
                        <li>
                            <SquareMousePointer aria-hidden="true" />
                            <span>
                                {t(
                                    'Approve on that new page, and keep only one approval page open.',
                                )}
                            </span>
                        </li>
                    </ul>
                    <p className="connect-muted">
                        {t(
                            'In Claude Code, run /mcp, choose the Elancer server, then Authenticate.',
                        )}
                    </p>
                </section>

                <div className="connect-actions">
                    <Link
                        href={signedIn ? '/settings/agents' : '/'}
                        className="two-factor-submit"
                    >
                        {signedIn ? t('Open AI agents') : t('Elancer home')}
                    </Link>
                </div>
            </main>
        </div>
    );
}
