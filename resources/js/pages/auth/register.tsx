import { Form, Head, Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import ElancerSiteHeader from '@/components/elancer-site-header';
import AuthField from '@/components/auth-field';
import AuthSocial from '@/components/auth-social';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import '../../../css/elancer-registration.css';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <div className="elancer-registration">
            <Head title="Join Elancer" />
            <a href="#registration-form" className="registration-skip">
                Skip to registration
            </a>
            <ElancerSiteHeader registration />

            <main className="registration-main">
                <section
                    className="registration-form-panel"
                    aria-labelledby="registration-title"
                >
                    <div className="registration-form-content">
                        <div className="registration-form-heading">
                            <h1 id="registration-title">
                                Make yourself at home.
                            </h1>
                            <p>
                                Create your Elancer account. A place for your
                                skills, your ideas, and whatever comes next.
                            </p>
                        </div>
                        <Form
                            {...store.form()}
                            id="registration-form"
                            tabIndex={-1}
                            resetOnSuccess={[
                                'password',
                                'password_confirmation',
                            ]}
                            disableWhileProcessing
                            className="registration-form"
                        >
                            {({ processing, errors }) => (
                                <>
                                    {Object.keys(errors).length > 0 && (
                                        <p
                                            className="registration-error-summary"
                                            role="alert"
                                        >
                                            We couldn’t create your account.
                                            Check the highlighted fields below.
                                        </p>
                                    )}
                                    <fieldset
                                        disabled={processing}
                                        aria-busy={processing}
                                    >
                                        <legend className="sr-only">
                                            Account details
                                        </legend>
                                        <AuthField
                                            id="name"
                                            name="name"
                                            label="Full name"
                                            autoComplete="name"
                                            placeholder="Your full name"
                                            maxLength={255}
                                            required
                                            error={errors.name}
                                        />
                                        <AuthField
                                            id="email"
                                            name="email"
                                            label="Email address"
                                            type="email"
                                            autoComplete="email"
                                            placeholder="you@example.com"
                                            maxLength={255}
                                            required
                                            error={errors.email}
                                        />
                                        <AuthField
                                            id="password"
                                            name="password"
                                            label="Password"
                                            type="password"
                                            autoComplete="new-password"
                                            placeholder="Create a password"
                                            passwordrules={passwordRules}
                                            required
                                            error={errors.password}
                                        />
                                        <AuthField
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            label="Confirm password"
                                            type="password"
                                            autoComplete="new-password"
                                            placeholder="Repeat your password"
                                            passwordrules={passwordRules}
                                            required
                                            error={errors.password_confirmation}
                                        />
                                        <button
                                            type="submit"
                                            className="registration-submit"
                                            disabled={processing}
                                            data-test="register-user-button"
                                        >
                                            {processing && <Spinner />}
                                            <span aria-live="polite">
                                                {processing
                                                    ? 'Creating your account…'
                                                    : 'Create account'}
                                            </span>
                                        </button>
                                    </fieldset>
                                    <p className="registration-email-note">
                                        <Mail size={18} aria-hidden="true" />
                                        <span>
                                            We’ll email you a verification link
                                            after you register.
                                        </span>
                                    </p>
                                </>
                            )}
                        </Form>
                        <AuthSocial />
                        <p className="registration-login-note">
                            Already have an account?{' '}
                            <Link href={login()}>Log in</Link>
                        </p>
                    </div>
                </section>
                <aside
                    className="registration-art"
                    aria-labelledby="registration-art-title"
                >
                    <div className="registration-art-copy">
                        <h2 id="registration-art-title">
                            A little space for your next big idea.
                        </h2>
                        <p>
                            For independent minds and people who love what they
                            do.
                        </p>
                    </div>
                    <img
                        src="/images/registration-collaboration.png"
                        alt="Two independent creatives collaborating on a design at a shared table."
                        width="1536"
                        height="1024"
                    />
                    <div className="registration-art-caption">
                        <span>Find your people.</span>
                        <span>Make good work.</span>
                    </div>
                </aside>
            </main>
        </div>
    );
}
