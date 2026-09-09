import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';

type FieldProps = ComponentProps<'input'> & {
    id: string;
    label: string;
    error?: string;
};

// Each password field owns its visibility state independently.
export default function AuthField({
    id,
    label,
    error,
    type = 'text',
    ...props
}: FieldProps) {
    const [visible, setVisible] = useState(false);
    const isPassword = type === 'password';

    return (
        <div className="registration-field">
            <label htmlFor={id}>{label}</label>
            <div className="registration-input-wrap">
                <input
                    {...props}
                    id={id}
                    type={isPassword && visible ? 'text' : type}
                    className={
                        isPassword
                            ? 'registration-input registration-input-password'
                            : 'registration-input'
                    }
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${id}-error` : undefined}
                />
                {isPassword && (
                    <button
                        type="button"
                        className="registration-reveal"
                        onClick={() => setVisible((current) => !current)}
                        aria-label={`${visible ? 'Hide' : 'Show'} ${label.toLowerCase()}`}
                        aria-pressed={visible}
                        aria-controls={id}
                    >
                        {visible ? (
                            <EyeOff size={18} aria-hidden="true" />
                        ) : (
                            <Eye size={18} aria-hidden="true" />
                        )}
                    </button>
                )}
            </div>
            <InputError id={`${id}-error`} message={error} aria-live="polite" />
        </div>
    );
}
