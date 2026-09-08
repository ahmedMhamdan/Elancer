import { Form, Head, Link } from '@inertiajs/react';
import { Eye, EyeOff, Mail, MoveUpRight } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import ElancerWordmark from '@/components/elancer-wordmark';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import { home, login } from '@/routes';
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
    return (
        <div className="elancer-registration">
            <Head title="Join Elancer" />
            <a href="#registration-form" className="registration-skip">
                Skip to registration
            </a>
            <header className="registration-header">
                <Link href={home()} aria-label="Elancer home">
                    <ElancerWordmark />
                </Link>
                <p>
                    Already a member? <Link href={login()}>Log in</Link>
                </p>
            </header>

            <main className="registration-main">
                <section
                    className="registration-story"
                    aria-labelledby="registration-story-title"
                >
                    <div className="registration-story-copy">
                        <h1 id="registration-story-title">
                            Good work starts with a connection.
                        </h1>
                        <p>
                            A place for independent talent and people with
                            something to build. Start with your Elancer account.
                        </p>
                    </div>
                    <div className="registration-path">
                        <h2>Your next steps</h2>
                        <ol>
                            <li>
                                <span
                                    className="registration-step"
                                    aria-hidden="true"
                                >
                                    1
                                </span>
                                <div>
                                    <strong>Create your account</strong>
                                    <p>A few details to get started.</p>
                                </div>
                            </li>
                            <li>
                                <span
                                    className="registration-step"
                                    aria-hidden="true"
                                >
                                    2
                                </span>
                                <div>
                                    <strong>Verify your email</strong>
                                    <p>
                                        Follow the link we send to your inbox.
                                    </p>
                                </div>
                            </li>
                            <li>
                                <span
                                    className="registration-step"
                                    aria-hidden="true"
                                >
                                    3
                                </span>
                                <div>
                                    <strong>Make yourself known</strong>
                                    <p>Add your story to your profile.</p>
                                </div>
                            </li>
                        </ol>
                    </div>
                    <MoveUpRight
                        className="registration-story-mark"
                        strokeWidth={0.7}
                        aria-hidden="true"
                    />
                </section>

                <section
                    className="registration-form-panel"
                    aria-labelledby="registration-title"
                >
                    <div className="registration-form-content">
                        <div className="registration-form-heading">
                            <h2 id="registration-title">Join Elancer</h2>
                            <p>Let’s start with the basics.</p>
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
                                            placeholder="Enter your password again"
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
                    </div>
                </section>
            </main>
            <footer className="registration-footer">
                <span>Independent talent. Shared ambition.</span>
                <Link href={home()}>Back to Elancer</Link>
            </footer>
        </div>
    );
}
