import { usePage } from '@inertiajs/react';

/**
 * Plans 025 and 027: whether this visit is a demo session, and whether it may change things among demo
 * accounts. Plan 032 part 10: pages leave out what such a session may never use (files, the shared
 * profile, portfolio, reports, blocking, settings), which the server refuses anyway.
 */
export function useDemoSession() {
    const { auth } = usePage().props;
    return { demo: auth.demo === true, acting: auth.demoActing === true };
}
