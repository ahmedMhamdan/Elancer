// The payment providers' own marks (plan 032 part 12, Ahmed: "Real logos"), drawn in the current colour so each sits
// white on its brand colour. Stripe's "S" is the Simple Icons glyph (https://simpleicons.org, icon data CC0-1.0); the
// Moyasar mark is the path of https://docs.moyasar.com/img/logo.svg. Both are their owners' trademarks, shown only to
// name the provider a test payment went through. See THIRD_PARTY_NOTICES.md.
import type { SVGProps } from 'react';

export function StripeLogo(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
            {...props}
        >
            <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.594-7.305h.003z" />
        </svg>
    );
}

export function MoyasarLogo(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 429.63 413.97"
            fill="currentColor"
            aria-hidden="true"
            {...props}
        >
            <path d="M64.71,2.7c38.7-9.73,77.4,7.36,96.27,42.53l37.11,69.15-32.97,61.43-55.33-103.11c-9.4-17.52-26.04-14.88-30.92-13.66-4.88,1.23-20.78,6.77-20.78,26.65v242.57c0,19.88,15.91,25.43,20.78,26.65,4.88,1.24,21.52,3.86,30.92-13.66L268.64,45.23c18.87-35.17,57.57-52.26,96.27-42.53,38.71,9.73,64.71,43.08,64.71,82.99v242.57c0,39.91-26.01,73.27-64.72,82.99-7.24,1.82-14.48,2.7-21.59,2.7-30.89,0-59.33-16.64-74.67-45.23l-37.42-69.74,32.97-61.43,55.65,103.7c9.4,17.52,26.05,14.89,30.92,13.66,4.88-1.23,20.78-6.77,20.78-26.65V85.7c0-19.88-15.91-25.43-20.78-26.65-1.52-.38-4.2-.9-7.44-.9-7.13,0-17.01,2.52-23.48,14.56l-158.85,296.03c-15.34,28.59-43.78,45.23-74.67,45.23-7.11,0-14.35-.88-21.59-2.7C26.01,401.54,0,368.18,0,328.27V85.7C0,45.79,26.01,12.43,64.71,2.7Z" />
        </svg>
    );
}
