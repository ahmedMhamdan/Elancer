// Reuses Elancer auth styling and marks; provider button structure follows TailAdmin/auth/SignInForm.tsx.
import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import InputError from '@/components/input-error';
export default function AuthSocial() {
    const { t } = useTranslation();
    const { socialProviders = {}, errors } = usePage<{
        socialProviders?: Record<string, boolean>;
    }>().props;
    return (
        <>
            <div className="registration-divider">
                <span>{t('or continue with')}</span>
            </div>
            <div
                className="registration-social"
                aria-label={t('Other sign-in methods')}
            >
                {['google', 'github'].map((provider) => {
                    const label = provider === 'google' ? 'Google' : 'GitHub';
                    const content = (
                        <>
                            <img
                                src={'/images/' + provider + '-mark.svg'}
                                className={
                                    provider === 'github'
                                        ? 'registration-github-mark'
                                        : undefined
                                }
                                width="20"
                                height="20"
                                alt=""
                            />
                            {t('Continue with')} {label}
                        </>
                    );
                    return socialProviders[provider] ? (
                        <a
                            key={provider}
                            href={'/auth/' + provider + '/redirect'}
                        >
                            {content}
                        </a>
                    ) : (
                        <button key={provider} type="button" disabled>
                            {content}
                            <span>{t('Unavailable')}</span>
                        </button>
                    );
                })}
            </div>
            <InputError message={errors.social} />
            {(!socialProviders.google || !socialProviders.github) && (
                <p className="registration-provider-notice" role="status">
                    {t(
                        'Some sign-in providers are not available yet. You can use email to continue.',
                    )}
                </p>
            )}
        </>
    );
}
