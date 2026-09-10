// Adapted from TailAdmin/src/components/ui/button/Button.tsx (MIT).
// Demo structure retained; Elancer theme tokens and native accessible form attributes added.
// See THIRD_PARTY_NOTICES.md.
import type { ReactNode, ButtonHTMLAttributes } from 'react';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    children: ReactNode; // Button text or content
    size?: 'sm' | 'md'; // Button size
    variant?: 'primary' | 'outline'; // Button variant
    startIcon?: ReactNode; // Icon before the text
    endIcon?: ReactNode; // Icon after the text
    onClick?: () => void; // Click handler
    disabled?: boolean; // Disabled state
    className?: string; // Disabled state
}

const Button: React.FC<ButtonProps> = ({
    children,
    size = 'md',
    variant = 'primary',
    startIcon,
    endIcon,
    onClick,
    className = '',
    disabled = false,
    type = 'button',
    ...props
}) => {
    // Size Classes
    const sizeClasses = {
        sm: 'px-4 py-3 text-base',
        md: 'px-5 py-3.5 text-base',
    };

    // Variant Classes
    const variantClasses = {
        primary:
            'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 disabled:bg-primary/50',
        outline:
            'bg-background text-foreground ring-1 ring-inset ring-input hover:bg-muted     ',
    };

    return (
        <button
            {...props}
            type={type}
            className={`focus-visible:outline-ring inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg transition focus-visible:outline-2 focus-visible:outline-offset-4 ${className} ${
                sizeClasses[size]
            } ${variantClasses[variant]} ${
                disabled ? 'cursor-not-allowed opacity-50' : ''
            }`}
            onClick={onClick}
            disabled={disabled}
        >
            {startIcon && (
                <span className="flex items-center">{startIcon}</span>
            )}
            {children}
            {endIcon && <span className="flex items-center">{endIcon}</span>}
        </button>
    );
};

export default Button;
