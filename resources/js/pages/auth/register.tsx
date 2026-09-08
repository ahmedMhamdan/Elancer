import { Form, Head, Link } from '@inertiajs/react';
import { Eye, EyeOff, Mail } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import ElancerSiteHeader from '@/components/elancer-site-header';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import '../../../css/elancer-registration.css';

type Props = {
    passwordRules: string;
};

type FieldProps = ComponentProps<'input'> & {
    id: string;
    label: string;
    error?: string;
};

// Each password field owns its visibility state independently.
function RegistrationField({
    id,
    label,
    error,
    type = 'text',
    ...props
}: FieldProps) {
    const [visible, setVisible] = useState(false);
    const isPassword = type === 'password';

    return (
        <div className="registration-field">
            <label htmlFor={id}>{label}</label>
            <div className="registration-input-wrap">
                <input
                    {...props}
                    id={id}
                    type={isPassword && visible ? 'text' : type}
                    className={
                        isPassword
                            ? 'registration-input registration-input-password'
                            : 'registration-input'
                    }
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${id}-error` : undefined}
                />
                {isPassword && (
                    <button
                        type="button"
                        className="registration-reveal"
                        onClick={() => setVisible((current) => !current)}
                        aria-label={`${visible ? 'Hide' : 'Show'} ${label.toLowerCase()}`}
                        aria-pressed={visible}
                        aria-controls={id}
                    >
                        {visible ? (
                            <EyeOff size={18} aria-hidden="true" />
                        ) : (
                            <Eye size={18} aria-hidden="true" />
                        )}
                    </button>
                )}
            </div>
            <InputError id={`${id}-error`} message={error} aria-live="polite" />
        </div>
    );
}

export default function Register({ passwordRules }: Props) {
    const [providerNotice, setProviderNotice] = useState('');
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
                        <div
                            className="registration-social"
                            aria-label="Other sign-in methods"
                        >
                            <button
                                type="button"
                                onClick={() =>
                                    setProviderNotice(
                                        'Google sign-in is coming soon. You can create an account with email below.',
                                    )
                                }
                            >
                                <img
                                    src="/images/google-mark.svg"
                                    width="20"
                                    height="20"
                                    alt=""
                                />
                                Continue with Google
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    setProviderNotice(
                                        'GitHub sign-in is coming soon. You can create an account with email below.',
                                    )
                                }
                            >
                                <img
                                    className="registration-github-mark"
                                    src="/images/github-mark.svg"
                                    width="20"
                                    height="20"
                                    alt=""
                                />
                                Continue with GitHub
                            </button>
                        </div>
                        <p
                            className="registration-provider-notice"
                            role="status"
                        >
                            {providerNotice ||
                                'Google and GitHub sign-in coming soon.'}
                        </p>
                        <div className="registration-divider">
                            <span>or use your email</span>
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
                                        <RegistrationField
                                            id="name"
                                            name="name"
                                            label="Full name"
                                            autoComplete="name"
                                            placeholder="Your full name"
                                            maxLength={255}
                                            required
                                            error={errors.name}
                                        />
                                        <RegistrationField
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
                                        <RegistrationField
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
                                        <RegistrationField
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
