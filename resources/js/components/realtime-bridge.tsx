import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { useNotificationSentence } from '@/hooks/use-notification-sentence';
import { useTranslation } from '@/hooks/use-translation';
import { subscribe, type Signal } from '@/lib/realtime';

const chatProps = [
    'messages',
    'conversation',
    'conversations',
    'writable',
    'visibleThrough',
];

// Turns a live signal into fresh page data: the bell count everywhere, the open
// inbox or conversation on Messages, and a toast for a new notification.
export default function RealtimeBridge() {
    const page = usePage();
    const { t } = useTranslation();
    const sentence = useNotificationSentence();
    const { auth, realtime } = page.props;
    const chat = page.component.startsWith('messages/');
    const open =
        page.component === 'messages/show'
            ? (page.props.conversation as { id: number }).id
            : null;
    const handle = useRef<(signal: Signal) => void>(() => {});
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => {
        handle.current = (signal) => {
            // Several signals in quick succession become one request.
            window.clearTimeout(timer.current);
            timer.current = window.setTimeout(
                () =>
                    router.reload({
                        only: chat
                            ? ['notifications', ...chatProps]
                            : ['notifications'],
                    }),
                150,
            );
            // A message for the conversation already on screen needs no toast.
            if (
                !signal.notification ||
                (signal.conversation !== null && signal.conversation === open)
            )
                return;
            fetch('/notifications', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) =>
                    response.ok ? response.json() : Promise.reject(response),
                )
                .then(
                    (data: {
                        notifications: {
                            id: string;
                            kind: string | null;
                            title: string | null;
                            actor: string | null;
                        }[];
                    }) => {
                        const item = data.notifications.find(
                            ({ id }) => id === signal.notification,
                        );
                        if (!item) return;
                        toast(
                            [item.actor, sentence(item.kind), item.title]
                                .filter(Boolean)
                                .join(' '),
                            {
                                action: {
                                    label: t('Open'),
                                    onClick: () =>
                                        router.patch(
                                            `/notifications/${item.id}`,
                                        ),
                                },
                            },
                        );
                    },
                )
                .catch(() => {});
        };
    });
    useEffect(
        () =>
            subscribe(realtime, auth.user.id, (signal) =>
                handle.current(signal),
            ),
        [realtime, auth.user.id],
    );

    return null;
}
