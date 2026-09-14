import { useTranslation } from '@/hooks/use-translation';
import { Form, Head, Link } from '@inertiajs/react';
import AuthField from '@/components/auth-field';
import AuthSocial from '@/components/auth-social';
import ElancerSiteHeader from '@/components/elancer-site-header';
import PasskeyVerify from '@/components/passkey-verify';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import '../../../css/elancer-registration.css';

type Props = { status?: string; canResetPassword: boolean };

export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslation();

    return (
        <div className="elancer-registration elancer-login">
            <Head title={t('Log in to Elancer')} />
            <a href="#login-form" className="registration-skip">
                {t('Skip to login')}
            </a>
            <ElancerSiteHeader registration loginPage />
            <main className="registration-main">
                <section
                    className="registration-form-panel"
                    aria-labelledby="login-title"
                >
                    <div className="registration-form-content">
                        <div className="registration-form-heading">
                            <h1 id="login-title">
                                {t('Good to have you back.')}
                            </h1>
                            <p>
                                {t(
                                    'Log in to your Elancer account and pick up where you left off.',
                                )}
                            </p>
                        </div>
                        {status && (
                            <p className="login-status" role="status">
                                {status}
                            </p>
                        )}
                        <Form
                            {...store.form()}
                            id="login-form"
                            tabIndex={-1}
                            resetOnSuccess={['password']}
                            disableWhileProcessing
                            className="registration-form"
                        >
                            {({ processing, errors }) => (
                                <fieldset
                                    disabled={processing}
                                    aria-busy={processing}
                                >
                                    <legend className="sr-only">
                                        {t('Login details')}
                                    </legend>
                                    <AuthField
                                        id="email"
                                        name="email"
                                        label={t('Email address')}
                                        type="email"
                                        required
                                        autoComplete="email"
                                        placeholder="you@example.com"
                                        error={errors.email}
                                    />
                                    <AuthField
                                        id="password"
                                        name="password"
                                        label={t('Password')}
                                        type="password"
                                        required
                                        autoComplete="current-password"
                                        placeholder={t('Enter your password')}
                                        error={errors.password}
                                    />
                                    <div className="login-options">
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="remember"
                                                value="1"
                                            />
                                            {t('Remember me')}
                                        </label>
                                        {canResetPassword && (
                                            <Link href={request()}>
                                                {t('Forgot password?')}
                                            </Link>
                                        )}
                                    </div>
                                    <button
                                        type="submit"
                                        className="registration-submit"
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing && <Spinner />}
                                        <span aria-live="polite">
                                            {processing
                                                ? t('Logging in...')
                                                : t('Log in')}
                                        </span>
                                    </button>
                                </fieldset>
                            )}
                        </Form>
                        <AuthSocial />
                        <div className="login-passkey">
                            <PasskeyVerify hideSeparator />
                        </div>
                        <p className="registration-login-note">
                            {t('New to Elancer?')}{' '}
                            <Link href={register()}>
                                {t('Create an account')}
                            </Link>
                        </p>
                    </div>
                </section>
                <aside
                    className="registration-art"
                    aria-labelledby="login-art-title"
                >
                    <div className="registration-art-copy">
                        <h2 id="login-art-title">
                            {t('Your next chapter starts here.')}
                        </h2>
                        <p>
                            {t(
                                'A familiar place for your skills, your people, and the work you love.',
                            )}
                        </p>
                    </div>
                    <img
                        src="/images/login-welcome-back.png"
                        alt={t(
                            'A creative carrying a portfolio through the open doorway of a sunlit studio.',
                        )}
                        width="1536"
                        height="1024"
                    />
                    <div className="registration-art-caption">
                        <span>{t('Welcome back.')}</span>
                        <span>{t('Make good work.')}</span>
                    </div>
                </aside>
            </main>
        </div>
    );
}
