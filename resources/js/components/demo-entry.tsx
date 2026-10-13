// Plan 025 demo entry. Reuses the provider button structure of auth-social.tsx (TailAdmin
// auth/SignInForm.tsx): a labelled divider, then one bordered button per choice, with the
// role icons the onboarding page already uses.
import { router, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, Code2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

/** A visitor who does not want to register enters a prepared, view-only account. */
export default function DemoEntry() {
    const { t } = useTranslation();
    const { demoRoles = [], errors } = usePage<{
        demoRoles?: string[];
    }>().props;
    const [entering, setEntering] = useState<string | null>(null);
    if (demoRoles.length === 0) return null;
    return (
        <section className="registration-demo" aria-labelledby="demo-entry">
            <div className="registration-divider">
                <span id="demo-entry">{t('or look around first')}</span>
            </div>
            <div className="registration-social">
                {demoRoles.map((role) => (
                    <button
                        key={role}
                        type="button"
                        disabled={entering !== null}
                        data-test={'demo-' + role}
                        onClick={() =>
                            router.post(
                                '/demo/' + role,
                                {},
                                {
                                    onStart: () => setEntering(role),
                                    onFinish: () => setEntering(null),
                                },
                            )
                        }
                    >
                        {entering === role ? (
                            <Spinner />
                        ) : role === 'client' ? (
                            <BriefcaseBusiness size={20} aria-hidden="true" />
                        ) : (
                            <Code2 size={20} aria-hidden="true" />
                        )}
                        {role === 'client'
                            ? t('Explore as a client')
                            : t('Explore as a freelancer')}
                    </button>
                ))}
            </div>
            <InputError message={errors.demo} />
            <p className="registration-provider-notice">
                {t(
                    'No account needed. Demo accounts are shared with other visitors and view-only, with sample projects, proposals and contracts.',
                )}
            </p>
        </section>
    );
}
