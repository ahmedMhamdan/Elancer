// Adapted from TailAdmin/src/components/form/Label.tsx (MIT).
// Demo structure retained; Elancer theme tokens and native accessible form attributes added.
// See THIRD_PARTY_NOTICES.md.
import { FC, ReactNode } from 'react';
import { twMerge } from 'tailwind-merge';
import { clsx } from 'clsx';

interface LabelProps {
    htmlFor?: string;
    children: ReactNode;
    className?: string;
}

const Label: FC<LabelProps> = ({ htmlFor, children, className }) => {
    return (
        <label
            htmlFor={htmlFor}
            className={clsx(
                twMerge(
                    'mb-1.5 block text-base font-medium text-foreground ',
                    className,
                ),
            )}
        >
            {children}
        </label>
    );
};

export default Label;
