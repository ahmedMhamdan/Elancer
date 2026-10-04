import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import Button from '@/components/tailadmin/button';
import { useTranslation } from '@/hooks/use-translation';

type Preferences = { contracts: boolean; hiring: boolean; messages: boolean };

export default function NotificationSettings({
    preferences,
}: {
    preferences: Preferences;
}) {
    const { t } = useTranslation();
    const form = useForm(preferences);
    const options: { key: keyof Preferences; label: string; hint: string }[] = [
        {
            key: 'contracts',
            label: t('Offers and contracts'),
            hint: t(
                'Offers, funding, deliveries, revision requests, completion, reviews and cancellations.',
            ),
        },
        {
            key: 'hiring',
            label: t('Invitations and proposals'),
            hint: t('Invitations to apply and proposals on your projects.'),
        },
        {
            key: 'messages',
            label: t('Messages'),
            hint: t(
                'One email per conversation with unread messages, not one per message.',
            ),
        },
    ];
    return (
        <>
            <Head title={t('Notification settings')} />

            <h1 className="sr-only">{t('Notification settings')}</h1>

            <form
                className="space-y-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch('/settings/notifications', {
                        preserveScroll: true,
                    });
                }}
            >
                <Heading
                    variant="small"
                    title={t('Email notifications')}
                    description={t(
                        'Choose which updates are also sent to your email. The bell always shows every update, and security emails are always sent.',
                    )}
                />
                <fieldset className="space-y-4">
                    <legend className="sr-only">
                        {t('Email notifications')}
                    </legend>
                    {options.map(({ key, label, hint }) => (
                        <label
                            key={key}
                            className="flex min-h-11 cursor-pointer items-start gap-3"
                        >
                            <input
                                type="checkbox"
                                className="accent-primary mt-1 size-[18px] shrink-0"
                                checked={form.data[key]}
                                onChange={(event) =>
                                    form.setData(key, event.target.checked)
                                }
                            />
                            <span>
                                <span className="block font-medium">
                                    {label}
                                </span>
                                <span className="text-muted-foreground block text-sm">
                                    {hint}
                                </span>
                            </span>
                        </label>
                    ))}
                </fieldset>
                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={form.processing}>
                        {t('Save preferences')}
                    </Button>
                    {form.recentlySuccessful && (
                        <p
                            className="text-muted-foreground text-sm"
                            role="status"
                        >
                            {t('Saved')}
                        </p>
                    )}
                </div>
            </form>
        </>
    );
}

NotificationSettings.layout = {
    breadcrumbs: [
        { title: 'Notification settings', href: '/settings/notifications' },
    ],
};
