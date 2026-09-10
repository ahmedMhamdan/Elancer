// Adapted from TailAdmin/src/components/header/NotificationDropdown.tsx (MIT).
// Source: https://github.com/TailAdmin/free-react-tailwind-admin-dashboard
// Keeps the demo bell SVG, panel/header composition and close SVG.
// Elancer changes: palette tokens, responsive positioning, accessible dismissal,
// and an empty state until Ahmed implements recipient-scoped notifications.
import { useCallback, useId, useRef, useState } from 'react';
import { TailAdminDropdown } from '@/components/tailadmin-dropdown';

export default function NotificationDropdown() {
    const [isOpen, setIsOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const panelId = useId();
    const titleId = useId();
    const closeDropdown = useCallback(() => setIsOpen(false), []);

    function closeAndFocus() {
        closeDropdown();
        triggerRef.current?.focus();
    }

    return (
        <div className="workspace-notifications relative">
            <button
                ref={triggerRef}
                type="button"
                className="site-theme-toggle relative"
                aria-label="Notifications"
                aria-expanded={isOpen}
                aria-controls={isOpen ? panelId : undefined}
                onClick={() => setIsOpen((open) => !open)}
            >
                <svg
                    className="fill-current"
                    aria-hidden="true"
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        fillRule="evenodd"
                        clipRule="evenodd"
                        d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"
                        fill="currentColor"
                    />
                </svg>
            </button>
            <TailAdminDropdown
                isOpen={isOpen}
                onClose={closeDropdown}
                triggerRef={triggerRef}
                id={panelId}
                labelledBy={titleId}
                className="workspace-notification-panel"
            >
                <div className="border-border mb-3 flex items-center justify-between border-b pb-3">
                    <h2
                        id={titleId}
                        className="text-foreground text-lg font-semibold"
                    >
                        Notifications
                    </h2>
                    <button
                        type="button"
                        onClick={closeAndFocus}
                        className="site-theme-toggle"
                        aria-label="Close notifications"
                    >
                        <svg
                            className="fill-current"
                            aria-hidden="true"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                fillRule="evenodd"
                                clipRule="evenodd"
                                d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                </div>
                <div className="flex min-h-40 flex-col items-center justify-center gap-2 px-4 py-8 text-center">
                    <p className="text-foreground font-medium">
                        No notifications yet
                    </p>
                    <p className="text-muted-foreground text-sm">
                        Project and account updates will appear here.
                    </p>
                </div>
            </TailAdminDropdown>
        </div>
    );
}
