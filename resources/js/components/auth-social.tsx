import { useTranslation } from '@/hooks/use-translation';
import { useState } from 'react';

export default function AuthSocial() {
    const { t } = useTranslation();
    const [notice, setNotice] = useState('');
    return (
        <>
            <div className="registration-divider">
                <span>{t('or continue with')}</span>
            </div>
            <div
                className="registration-social"
                aria-label={t('Other sign-in methods')}
            >
                {['Google', 'GitHub'].map((provider) => (
                    <button
                        key={provider}
                        type="button"
                        onClick={() =>
                            setNotice(
                                'Social sign-in is coming soon. Please use the email form above.',
                            )
                        }
                    >
                        <img
                            src={`/images/${provider.toLowerCase()}-mark.svg`}
                            className={
                                provider === 'GitHub'
                                    ? 'registration-github-mark'
                                    : undefined
                            }
                            width="20"
                            height="20"
                            alt=""
                        />
                        {t('Continue with')} {provider}
                    </button>
                ))}
            </div>
            <p className="registration-provider-notice" role="status">
                {t(notice || 'Google and GitHub sign-in coming soon.')}
            </p>
        </>
    );
}
