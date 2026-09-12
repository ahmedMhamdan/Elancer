import ElancerWordmark from '@/components/elancer-wordmark';
import ThemeToggle from '@/components/theme-toggle';
import '../../../css/elancer-two-factor.css';
import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/passkey-verify';

export default function ConfirmPassword() {
    return (
        <div className="elancer-two-factor">
            <header className="two-factor-header">
                <Link
                    href="/"
                    aria-label="Elancer home"
                    className="two-factor-brand"
                >
                    <ElancerWordmark />
                </Link>
                <ThemeToggle />
            </header>
            <main className="two-factor-main">
                <div className="two-factor-intro">
                    <h1>Confirm it’s you.</h1>
                    <p>
                        Enter your password or use a passkey to continue
                        securely with your Elancer account.
                    </p>
                </div>
                <div className="mt-6 text-start">
                    <Head title="Confirm password" />

                    <PasskeyVerify
                        routes={{
                            options: confirmOptions(),
                            submit: confirmStore(),
                        }}
                        label="Confirm with passkey"
                        loadingLabel="Confirming..."
                        separator="Or confirm with password"
                    />

                    <Form {...store.form()} resetOnSuccess={['password']}>
                        {({ processing, errors }) => (
                            <div className="space-y-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        placeholder="Password"
                                        autoComplete="current-password"
                                        aria-invalid={!!errors.password}
                                        aria-describedby="confirm-password-error"
                                        autoFocus
                                    />

                                    <InputError
                                        id="confirm-password-error"
                                        role="alert"
                                        message={errors.password}
                                    />
                                </div>

                                <div className="flex items-center">
                                    <Button
                                        className="two-factor-submit"
                                        disabled={processing}
                                        data-test="confirm-password-button"
                                    >
                                        {processing && <Spinner />}
                                        Confirm password
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </div>
                <Link
                    href="/dashboard"
                    className="text-primary mt-6 inline-block min-h-11 py-3 underline"
                >
                    Back to workspace
                </Link>
            </main>
        </div>
    );
}
