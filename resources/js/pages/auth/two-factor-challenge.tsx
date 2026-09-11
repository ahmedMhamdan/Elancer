import { Form, Head, Link } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import ElancerWordmark from '@/components/elancer-wordmark';
import Input from '@/components/tailadmin/input';
import Label from '@/components/tailadmin/label';
import ThemeIcon from '@/components/theme-icon';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/hooks/use-appearance';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { home, login } from '@/routes';
import { store } from '@/routes/two-factor/login';
import '../../../css/elancer-two-factor.css';

export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);
    const [code, setCode] = useState('');
    const [requestError, setRequestError] = useState('');
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const nextTheme = resolvedAppearance === 'dark' ? 'light' : 'dark';

    return (
        <div className="elancer-two-factor">
            <Head title="Two-factor authentication" />
            <header className="two-factor-header">
                <Link
                    href={home()}
                    aria-label="Elancer home"
                    className="two-factor-brand"
                >
                    <ElancerWordmark />
                </Link>
                <button
                    type="button"
                    className="two-factor-theme"
                    aria-label={`Switch to ${nextTheme} mode`}
                    onClick={() => updateAppearance(nextTheme)}
                >
                    <ThemeIcon mode={nextTheme} />
                </button>
            </header>
            <main className="two-factor-main">
                <div className="two-factor-intro">
                    <h1>
                        {recovery ? 'Use a recovery code.' : 'One more step.'}
                    </h1>
                    <p>
                        {recovery
                            ? 'Enter one of the recovery codes you saved when you set up two-factor authentication.'
                            : 'Open your authenticator app and enter the six-digit code for your Elancer account.'}
                    </p>
                </div>
                <Form
                    {...store.form()}
                    resetOnError
                    resetOnSuccess
                    onStart={() => setRequestError('')}
                    onError={() => setCode('')}
                    onNetworkError={() => {
                        setRequestError('Could not connect. Please try again.');
                        return false;
                    }}
                    onHttpException={() => {
                        setRequestError(
                            'We could not verify your code. Please try again.',
                        );
                        return false;
                    }}
                >
                    {({ errors, processing, clearErrors }) => (
                        <fieldset
                            disabled={processing}
                            aria-busy={processing}
                            className="two-factor-fields"
                        >
                            <legend className="sr-only">
                                Two-factor authentication
                            </legend>
                            <div>
                                <Label
                                    htmlFor={
                                        recovery ? 'recovery_code' : 'code'
                                    }
                                >
                                    {recovery
                                        ? 'Recovery code'
                                        : 'Authentication code'}
                                </Label>
                                {recovery ? (
                                    <Input
                                        key="recovery"
                                        id="recovery_code"
                                        name="recovery_code"
                                        type="text"
                                        placeholder="Enter recovery code"
                                        autoComplete="off"
                                        autoCapitalize="none"
                                        spellCheck={false}
                                        autoFocus
                                        required
                                        disabled={processing}
                                        aria-invalid={Boolean(
                                            errors.recovery_code,
                                        )}
                                        aria-describedby="two-factor-error"
                                    />
                                ) : (
                                    <InputOTP
                                        key="authenticator"
                                        id="code"
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        pushPasswordManagerStrategy="none"
                                        value={code}
                                        onChange={setCode}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        autoFocus
                                        required
                                        aria-invalid={Boolean(errors.code)}
                                        aria-describedby="two-factor-error"
                                        containerClassName="two-factor-otp"
                                    >
                                        <InputOTPGroup className="two-factor-otp-group">
                                            {Array.from(
                                                { length: OTP_MAX_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                        className="two-factor-slot"
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                )}
                                <p
                                    id="two-factor-error"
                                    className="two-factor-error"
                                    role="alert"
                                >
                                    {requestError ||
                                        (recovery
                                            ? errors.recovery_code
                                            : errors.code)}
                                </p>
                            </div>
                            <button
                                type="submit"
                                className="two-factor-submit"
                                disabled={
                                    processing ||
                                    (!recovery &&
                                        code.length !== OTP_MAX_LENGTH)
                                }
                            >
                                {processing && <Spinner />}
                                {processing
                                    ? 'Verifying…'
                                    : 'Verify and continue'}
                            </button>
                            <div className="two-factor-alternative">
                                <p>
                                    {recovery
                                        ? 'Have access to your authenticator?'
                                        : 'Can’t access your authenticator?'}
                                </p>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRecovery(!recovery);
                                        clearErrors();
                                        setCode('');
                                        setRequestError('');
                                    }}
                                >
                                    {recovery
                                        ? 'Use an authentication code'
                                        : 'Use a recovery code'}
                                </button>
                            </div>
                        </fieldset>
                    )}
                </Form>
                <Link href={login()} className="two-factor-back">
                    Back to log in
                </Link>
            </main>
            <footer className="two-factor-footer">
                Your account. An extra layer of protection.
            </footer>
        </div>
    );
}
