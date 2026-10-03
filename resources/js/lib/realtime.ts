// One connection per signed-in member, kept across Inertia visits. The server
// sends identifiers only on the member's private channel; listeners then ask the
// application for what that member may see.
import { router } from '@inertiajs/react';

export type RealtimeConfig = {
    driver: 'reverb' | 'pusher';
    key: string;
    host: string | null;
    port: number | null;
    secure: boolean;
    cluster: string | null;
};
export type Signal = {
    notification: string | null;
    conversation: number | null;
};

const listeners = new Set<(signal: Signal) => void>();
let member: number | null = null;
let disconnect: (() => void) | null = null;

const emit = (signal: Signal) =>
    listeners.forEach((listener) => listener(signal));

function stop() {
    disconnect?.();
    disconnect = null;
    member = null;
}

// The cookie stays current across sign-in and session renewal, unlike a token rendered once.
function xsrf(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function connect(config: RealtimeConfig, id: number) {
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
        import('laravel-echo'),
        import('pusher-js'),
    ]);
    if (member !== id) return;
    const shared = {
        key: config.key,
        Pusher,
        channelAuthorization: {
            endpoint: '/broadcasting/auth',
            transport: 'ajax' as const,
            headersProvider: () => ({ 'X-XSRF-TOKEN': xsrf() }),
        },
    };
    const echo =
        config.driver === 'reverb'
            ? new Echo({
                  ...shared,
                  broadcaster: 'reverb',
                  wsHost: config.host ?? window.location.hostname,
                  wsPort: config.port ?? 80,
                  wssPort: config.port ?? 443,
                  forceTLS: config.secure,
                  enabledTransports: ['ws', 'wss'],
              })
            : new Echo({
                  ...shared,
                  broadcaster: 'pusher',
                  cluster: config.cluster ?? 'mt1',
                  forceTLS: true,
              });
    echo.private('workspace.' + id).listen('.signal', emit);
    // Anything sent while the connection was down is picked up on reconnection.
    let connected = false;
    echo.connector.pusher.connection.bind('connected', () => {
        if (connected) emit({ notification: null, conversation: null });
        connected = true;
    });
    disconnect = () => echo.disconnect();
}

// A member who signs out, or is replaced by another, must stop receiving the previous channel.
router.on('navigate', (event) => {
    const id = event.detail.page.props.auth?.user?.id ?? null;
    if (member !== null && id !== member) stop();
});

export function subscribe(
    config: RealtimeConfig | null,
    id: number,
    listener: (signal: Signal) => void,
): () => void {
    listeners.add(listener);
    if (config && member !== id) {
        stop();
        member = id;
        connect(config, id).catch(() => {
            if (member === id) member = null;
        });
    }
    return () => {
        listeners.delete(listener);
    };
}
