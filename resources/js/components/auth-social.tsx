import { useState } from 'react';

export default function AuthSocial() {
    const [notice, setNotice] = useState('');
    return (
        <>
            <div className="registration-divider">
                <span>or continue with</span>
            </div>
            <div
                className="registration-social"
                aria-label="Other sign-in methods"
            >
                {['Google', 'GitHub'].map((provider) => (
                    <button
                        key={provider}
                        type="button"
                        onClick={() =>
                            setNotice(
                                `${provider} sign-in is coming soon. Please use the email form above.`,
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
                        Continue with {provider}
                    </button>
                ))}
            </div>
            <p className="registration-provider-notice" role="status">
                {notice || 'Google and GitHub sign-in coming soon.'}
            </p>
        </>
    );
}
