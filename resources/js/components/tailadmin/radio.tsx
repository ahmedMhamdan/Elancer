// Adapted from TailAdmin/src/components/form/input/Radio.tsx (MIT).
// Demo structure retained; Elancer theme tokens and native accessible form attributes added.
// See THIRD_PARTY_NOTICES.md.
import type React from 'react';
interface RadioProps {
    id: string; // Unique ID for the radio button
    name: string; // Radio group name
    value: string; // Value of the radio button
    checked: boolean; // Whether the radio button is checked
    label: React.ReactNode; // Label for the radio button
    onChange: (value: string) => void; // Handler for value change
    className?: string; // Optional additional classes
    disabled?: boolean; // Optional disabled state for the radio button
}

const Radio: React.FC<RadioProps> = ({
    id,
    name,
    value,
    checked,
    label,
    onChange,
    className = '',
    disabled = false,
}) => {
    return (
        <label
            htmlFor={id}
            className={`relative flex cursor-pointer items-center gap-3 text-base font-medium select-none ${
                disabled
                    ? 'text-muted-foreground cursor-not-allowed'
                    : 'text-foreground'
            } ${className}`}
        >
            <input
                id={id}
                name={name}
                type="radio"
                value={value}
                checked={checked}
                onChange={() => !disabled && onChange(value)} // Prevent onChange when disabled
                className="peer sr-only"
                disabled={disabled} // Disable input
            />
            <span
                className={`peer-focus-visible:ring-ring flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-[1.25px] peer-focus-visible:ring-2 peer-focus-visible:ring-offset-4 ${
                    checked
                        ? 'border-primary bg-primary'
                        : 'border-input bg-transparent'
                } ${disabled ? 'bg-muted border-input ' : ''}`}
            >
                <span
                    className={`bg-background h-2 w-2 rounded-full ${
                        checked ? 'block' : 'hidden'
                    }`}
                ></span>
            </span>
            {label}
        </label>
    );
};

export default Radio;
