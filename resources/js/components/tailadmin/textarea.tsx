// Adapted from TailAdmin/src/components/form/input/TextArea.tsx (MIT).
// Demo structure retained; Elancer theme tokens and native accessible form attributes added.
// See THIRD_PARTY_NOTICES.md.
import type React from 'react';

interface TextareaProps extends Omit<
    React.TextareaHTMLAttributes<HTMLTextAreaElement>,
    'onChange'
> {
    placeholder?: string; // Placeholder text
    rows?: number; // Number of rows
    value?: string; // Current value
    onChange?: (value: string) => void; // Change handler
    className?: string; // Additional CSS classes
    disabled?: boolean; // Disabled state
    error?: boolean; // Error state
    hint?: string; // Hint text to display
}

const TextArea: React.FC<TextareaProps> = ({
    placeholder = 'Enter your message', // Default placeholder
    rows = 3, // Default number of rows
    value = '', // Default value
    onChange, // Callback for changes
    className = '', // Additional custom styles
    disabled = false, // Disabled state
    error = false, // Error state
    hint = '', // Default hint text
    ...props
}) => {
    const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
        if (onChange) {
            onChange(e.target.value);
        }
    };

    let textareaClasses = `w-full rounded-lg border px-4 py-2.5 text-base shadow-xs focus:outline-hidden ${className} `;

    if (disabled) {
        textareaClasses += ` bg-muted opacity-50 text-muted-foreground border-input cursor-not-allowed    `;
    } else if (error) {
        textareaClasses += ` bg-transparent  border-input focus:border-destructive focus:ring-3 focus:ring-destructive/10    `;
    } else {
        textareaClasses += ` bg-transparent text-foreground  text-foreground border-input focus:border-primary focus:ring-3 focus:ring-ring/10    `;
    }

    return (
        <div className="relative">
            <textarea
                {...props}
                placeholder={placeholder}
                rows={rows}
                value={value}
                onChange={handleChange}
                disabled={disabled}
                className={textareaClasses}
            />
            {hint && (
                <p
                    className={`mt-2 text-base ${
                        error ? 'text-destructive' : 'text-muted-foreground '
                    }`}
                >
                    {hint}
                </p>
            )}
        </div>
    );
};

export default TextArea;
