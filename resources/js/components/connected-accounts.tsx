import { Form, usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
export type SocialAccountProps = {
    connectedProviders: string[];
    socialProviders: Record<string, boolean>;
};
export default function ConnectedAccounts({
    connectedProviders,
    socialProviders,
    confirm = false,
}: SocialAccountProps & { confirm?: boolean }) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    return (
        <section className="space-y-4">
            <Heading
                variant="small"
                title={
                    confirm
                        ? t('Confirm with a connected account')
                        : t('Connected accounts')
                }
                description={
                    confirm
                        ? t('Choose an account already connected to Elancer.')
                        : t(
                              'Connect a provider to sign in without an Elancer password.',
                          )
                }
            />
            <InputError message={errors.social} />
            <div className="flex flex-wrap gap-3">
                {['google', 'github']
                    .filter((p) => !confirm || connectedProviders.includes(p))
                    .map((provider) => {
                        const connected = connectedProviders.includes(provider);
                        const name =
                            provider === 'google' ? 'Google' : 'GitHub';
                        const action =
                            '/settings/social/' +
                            provider +
                            (confirm ? '/confirm' : '');
                        return (
                            <Form
                                key={provider}
                                action={action}
                                method={
                                    connected && !confirm ? 'delete' : 'post'
                                }
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={
                                            processing ||
                                            ((!connected || confirm) &&
                                                !socialProviders[provider])
                                        }
                                    >
                                        {confirm
                                            ? t('Confirm with :provider', {
                                                  provider: name,
                                              })
                                            : connected
                                              ? t('Disconnect :provider', {
                                                    provider: name,
                                                })
                                              : t('Connect :provider', {
                                                    provider: name,
                                                })}
                                    </Button>
                                )}
                            </Form>
                        );
                    })}
            </div>
            {!confirm && (
                <p className="text-muted-foreground text-sm">
                    {t(
                        'Keep at least one usable sign-in method. Add a password, passkey or another provider first.',
                    )}
                </p>
            )}
        </section>
    );
}
