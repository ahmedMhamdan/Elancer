// Plan 033: the page an AI app's Connect button opens. It reuses the focused sign-in shell of the
// two-factor and confirm-password pages (elancer-two-factor.css). The two forms are plain HTML posts:
// approving or turning the app down sends the browser on to the app's own address, which an Inertia
// visit could not follow.
import { Head, Link } from '@inertiajs/react';
import { Check, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import ElancerWordmark from '@/components/elancer-wordmark';
import LanguageToggle from '@/components/language-toggle';
import ThemeToggle from '@/components/theme-toggle';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-two-factor.css';
import '../../../css/elancer-connect.css';

type Props = {
    app: { name: string; returnsTo: string };
    account: { name: string; email: string };
    refusal: string | null;
    form: {
        action: string;
        csrf: string;
        state: string;
        clientId: string;
        authToken: string;
    };
};

export default function Authorize({ app, account, refusal, form }: Props) {
    const { t } = useTranslation();
    const [sending, setSending] = useState(false);
    const can = [
        t('Read your account details and whether you can apply'),
        t('Search and read public projects'),
        t('Read your proposals, save drafts, and submit or withdraw them'),
        t('Read your projects and the proposals they received'),
    ];
    const fields = (
        <>
            <input type="hidden" name="_token" value={form.csrf} />
            <input type="hidden" name="state" value={form.state} />
            <input type="hidden" name="client_id" value={form.clientId} />
            <input type="hidden" name="auth_token" value={form.authToken} />
        </>
    );

    return (
        <div className="elancer-two-factor">
            <Head title={t('Connect an app')} />
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
                    <h1>{t('Connect :app to Elancer?', { app: app.name })}</h1>
                    <p>
                        {t('This app wants to use your account, :name.', {
                            name: account.name,
                        })}{' '}
                        <bdi dir="ltr">{account.email}</bdi>
                    </p>
                </div>

                <section className="connect-card" aria-labelledby="connect-can">
                    <h2 id="connect-can">{t('It will be able to')}</h2>
                    <ul>
                        {can.map((line) => (
                            <li key={line}>
                                <Check aria-hidden="true" />
                                <span>{line}</span>
                            </li>
                        ))}
                    </ul>
                    <p className="connect-muted">
                        {t(
                            'It cannot send messages or offers, fund or change contracts, or change your settings.',
                        )}
                    </p>
                </section>

                <dl className="connect-facts">
                    <div>
                        <dt>{t('After you approve, you return to')}</dt>
                        <dd>
                            <code dir="ltr">{app.returnsTo}</code>
                        </dd>
                    </div>
                </dl>
                <p className="connect-muted connect-note">
                    {t(
                        'Elancer does not check who made an app. Approve only an app you started yourself. You can disconnect it at any time in Settings, API tokens.',
                    )}
                </p>

                {refusal && (
                    <div role="alert" className="connect-refusal">
                        <TriangleAlert aria-hidden="true" />
                        <p>{refusal}</p>
                    </div>
                )}

                <div className="connect-actions">
                    {!refusal && (
                        <form
                            method="post"
                            action={form.action}
                            onSubmit={() => setSending(true)}
                        >
                            {fields}
                            <button
                                type="submit"
                                className="two-factor-submit"
                                disabled={sending}
                            >
                                {t('Approve')}
                            </button>
                        </form>
                    )}
                    <form
                        method="post"
                        action={form.action}
                        onSubmit={() => setSending(true)}
                    >
                        {fields}
                        <input type="hidden" name="_method" value="DELETE" />
                        <button
                            type="submit"
                            className="connect-cancel"
                            disabled={sending}
                        >
                            {t('Cancel')}
                        </button>
                    </form>
                </div>
            </main>
        </div>
    );
}
