// Adapted from TailAdmin/src/components/form/input/InputField.tsx (MIT).
// Demo structure retained; Elancer theme tokens and native accessible form attributes added.
// See THIRD_PARTY_NOTICES.md.
import type React from 'react';
import type { FC } from 'react';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    type?: React.HTMLInputTypeAttribute;
    id?: string;
    name?: string;
    placeholder?: string;
    value?: string | number;
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
    className?: string;
    min?: string;
    max?: string;
    step?: number;
    disabled?: boolean;
    success?: boolean;
    error?: boolean;
    hint?: string;
}

const Input: FC<InputProps> = ({
    type = 'text',
    id,
    name,
    placeholder,
    value,
    onChange,
    className = '',
    min,
    max,
    step,
    disabled = false,
    success = false,
    error = false,
    hint,
    ...props
}) => {
    let inputClasses = ` h-11 w-full rounded-lg border appearance-none px-4 py-2.5 text-base shadow-xs placeholder:text-muted-foreground focus:outline-hidden focus:ring-3     ${className}`;

    if (disabled) {
        inputClasses += ` text-muted-foreground border-input opacity-40 bg-muted cursor-not-allowed    opacity-40`;
    } else if (error) {
        inputClasses += `  border-destructive focus:border-destructive focus:ring-destructive/20   `;
    } else if (success) {
        inputClasses += `  border-primary focus:border-primary focus:ring-primary/20   `;
    } else {
        inputClasses += ` bg-transparent text-foreground border-input focus:border-primary focus:ring-ring/20    `;
    }

    return (
        <div className="relative">
            <input
                {...props}
                type={type}
                id={id}
                name={name}
                placeholder={placeholder}
                value={value}
                onChange={onChange}
                min={min}
                max={max}
                step={step}
                disabled={disabled}
                className={inputClasses}
            />

            {hint && (
                <p
                    className={`mt-1.5 text-xs ${
                        error
                            ? 'text-destructive'
                            : success
                              ? 'text-primary'
                              : 'text-muted-foreground'
                    }`}
                >
                    {hint}
                </p>
            )}
        </div>
    );
};

export default Input;
