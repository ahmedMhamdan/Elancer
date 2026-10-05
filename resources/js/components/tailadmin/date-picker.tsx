// Adapted from TailAdmin/src/components/form/date-picker.tsx (MIT), which wraps flatpickr (MIT).
// Demo structure and calendar icon retained; made a controlled field with Elancer theme tokens,
// Arabic month and weekday names, RTL layout and an optional time. See THIRD_PARTY_NOTICES.md.
import type flatpickr from 'flatpickr';
import { useEffect, useRef } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import '../../../css/elancer-datepicker.css';

type Props = {
    id: string;
    // 'YYYY-MM-DD', or 'YYYY-MM-DDTHH:mm' with time; empty when nothing is chosen.
    value: string;
    onChange: (value: string) => void;
    time?: boolean;
    min?: 'today';
    placeholder?: string;
};

export default function DatePicker({
    id,
    value,
    onChange,
    time = false,
    min,
    placeholder,
}: Props) {
    const { locale } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const picker = useRef<flatpickr.Instance | null>(null);
    const changed = useRef(onChange);
    changed.current = onChange;
    const current = useRef(value);
    current.current = value;
    const rtl = locale === 'ar';

    useEffect(() => {
        let cancelled = false;
        let instance: flatpickr.Instance | null = null;
        // The calendar needs a browser, so it is loaded here and never during server rendering.
        void Promise.all([
            import('flatpickr'),
            rtl ? import('flatpickr/dist/l10n/ar.js') : null,
        ]).then(([library, arabic]) => {
            if (cancelled || !input.current) return;
            instance = library.default(input.current, {
                mode: 'single',
                static: true,
                monthSelectorType: 'static',
                enableTime: time,
                time_24hr: true,
                dateFormat: time ? 'Y-m-dTH:i' : 'Y-m-d',
                altInput: true,
                altFormat: time ? 'j F Y, H:i' : 'j F Y',
                minDate: min,
                locale: arabic ? arabic.Arabic : 'default',
                defaultDate: current.current || undefined,
                onChange: (_dates, text) => changed.current(text),
            });
            // The visible field carries the id, so the label and assistive technology reach it.
            if (instance.altInput) {
                input.current.removeAttribute('id');
                instance.altInput.id = id;
            }
            picker.current = instance;
        });
        return () => {
            cancelled = true;
            picker.current = null;
            instance?.destroy();
            input.current?.setAttribute('id', id);
        };
    }, [id, time, min, rtl]);

    useEffect(() => {
        const instance = picker.current;
        if (instance && instance.input.value !== value)
            instance.setDate(value || '', false);
    }, [value]);

    return (
        <div className="date-picker relative" dir={rtl ? 'rtl' : 'ltr'}>
            <input
                ref={input}
                id={id}
                defaultValue={value}
                readOnly
                placeholder={placeholder}
                className="border-input text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-ring/20 h-11 w-full appearance-none rounded-lg border bg-transparent px-4 py-2.5 pe-12 text-base shadow-xs focus:ring-3 focus:outline-hidden"
            />
            <span className="text-muted-foreground pointer-events-none absolute end-3 top-[22px] -translate-y-1/2">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    className="size-5"
                    aria-hidden="true"
                >
                    <path
                        fillRule="evenodd"
                        clipRule="evenodd"
                        d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z"
                        fill="currentColor"
                    />
                </svg>
            </span>
        </div>
    );
}
