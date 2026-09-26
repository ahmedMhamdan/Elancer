import { Form, Head, Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import AuthField from '@/components/auth-field';
import ElancerSiteHeader from '@/components/elancer-site-header';
import InputError from '@/components/input-error';
import '../../../css/elancer-registration.css';
export default function SocialComplete({ name }: { name: string }) {
    const { t } = useTranslation();
    return (
        <div className="elancer-registration">
            <Head title={t('Complete social signup')} />
            <ElancerSiteHeader registration />
            <main className="registration-main">
                <section className="registration-form-panel">
                    <div className="registration-form-content">
                        <h1 className="text-2xl font-semibold">
                            {t('Complete social signup')}
                        </h1>
                        <p>
                            {t(
                                'Enter your name and email. We will verify this address before you can use the marketplace. No password is required.',
                            )}
                        </p>
                        <Form
                            action="/auth/complete"
                            method="post"
                            className="space-y-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <AuthField
                                        id="name"
                                        name="name"
                                        label={t('Name')}
                                        defaultValue={name}
                                        required
                                        autoComplete="name"
                                        error={errors.name}
                                    />
                                    <AuthField
                                        id="email"
                                        name="email"
                                        type="email"
                                        label={t('Email address')}
                                        required
                                        autoComplete="email"
                                        error={errors.email}
                                    />
                                    <InputError message={errors.social} />
                                    <button
                                        type="submit"
                                        className="registration-submit"
                                        disabled={processing}
                                    >
                                        {t('Continue')}
                                    </button>
                                </>
                            )}
                        </Form>
                        <Link href="/login">{t('Back to login')}</Link>
                    </div>
                </section>
            </main>
        </div>
    );
}
