// Adapted from TailAdmin/src/components/ui/dropdown/Dropdown.tsx (MIT).
// See THIRD_PARTY_NOTICES.md. Adds scoped outside-click, Escape and focus handling.
import { useEffect, useRef } from 'react';
import type { ReactNode, RefObject } from 'react';

interface DropdownProps {
    isOpen: boolean;
    onClose: () => void;
    children: ReactNode;
    triggerRef: RefObject<HTMLButtonElement | null>;
    id: string;
    labelledBy: string;
    className?: string;
}

export function TailAdminDropdown({
    isOpen,
    onClose,
    children,
    triggerRef,
    id,
    labelledBy,
    className = '',
}: DropdownProps) {
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!isOpen) return;

        dropdownRef.current
            ?.querySelector<HTMLButtonElement>('button')
            ?.focus();

        const isInside = (target: EventTarget | null) =>
            target instanceof Node &&
            (dropdownRef.current?.contains(target) ||
                triggerRef.current?.contains(target));

        const handleClickOutside = (event: PointerEvent) => {
            if (!isInside(event.target)) onClose();
        };
        const handleFocusOutside = (event: FocusEvent) => {
            if (!isInside(event.target)) onClose();
        };
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
                triggerRef.current?.focus();
            }
        };

        document.addEventListener('pointerdown', handleClickOutside);
        document.addEventListener('focusin', handleFocusOutside);
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('pointerdown', handleClickOutside);
            document.removeEventListener('focusin', handleFocusOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen, onClose, triggerRef]);

    if (!isOpen) return null;

    return (
        <div
            ref={dropdownRef}
            id={id}
            role="region"
            aria-labelledby={labelledBy}
            className={`border-border bg-popover text-popover-foreground absolute z-40 mt-2 rounded-2xl border p-3 shadow-lg ${className}`}
        >
            {children}
        </div>
    );
}
