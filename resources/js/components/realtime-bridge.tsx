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
    'details',
    'writable',
    'visibleThrough',
];
// Plan 032 part 13: a refresh made while the browser tab is hidden does not read the open conversation.
const hidden = () =>
    document.hidden
        ? { 'X-Elancer-Hidden': '1' }
        : ({} as Record<string, string>);

// Turns a live signal into fresh page data: the bell count everywhere, the open
// inbox or conversation on Messages or in a workroom (plan 031), and a toast for a new notification.
export default function RealtimeBridge() {
    const page = usePage();
    const { t } = useTranslation();
    const sentence = useNotificationSentence();
    const { auth, realtime } = page.props;
    const chat = page.component.startsWith('messages/');
    const workroom = page.component === 'contracts/show';
    const open =
        page.component === 'messages/show'
            ? (page.props.conversation as { id: number }).id
            : workroom
              ? ((page.props.thread as { id: number } | null)?.id ?? null)
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
                            ? ['notifications', 'inbox', ...chatProps]
                            : workroom
                              ? ['notifications', 'inbox', 'thread']
                              : ['notifications', 'inbox'],
                        headers: hidden(),
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
    // Back in the tab: what arrived meanwhile is now on screen, so the open conversation is read.
    // Only the newest page reads a conversation, so an older one is left as it is.
    const waiting =
        page.component === 'messages/show' &&
        (page.props.messages as { current_page: number }).current_page === 1 &&
        (page.props.conversation as { unread: number }).unread > 0;
    useEffect(() => {
        if (!waiting) return;
        const back = () => {
            if (!document.hidden)
                router.reload({
                    only: [
                        'notifications',
                        'inbox',
                        'firstUnread',
                        'newMessages',
                        ...chatProps,
                    ],
                });
        };
        // The member may already be back by the time a refresh from the hidden tab answers.
        back();
        document.addEventListener('visibilitychange', back);
        return () => document.removeEventListener('visibilitychange', back);
    }, [waiting]);

    return null;
}
