'use client';

import * as React from 'react';
import * as ProgressPrimitive from '@radix-ui/react-progress';
import { cn } from '@/lib/utils';

// Supplied shadcn progress primitive; width-based fill supports either text direction.
const Progress = React.forwardRef<
    React.ElementRef<typeof ProgressPrimitive.Root>,
    React.ComponentPropsWithoutRef<typeof ProgressPrimitive.Root>
>(({ className, value = 0, ...props }, ref) => (
    <ProgressPrimitive.Root ref={ref} value={value} className={cn('relative h-2 w-full overflow-hidden rounded-full bg-muted', className)} {...props}>
        <ProgressPrimitive.Indicator className="h-full bg-primary motion-safe:transition-[width]" style={{ width: String(Math.max(0, Math.min(100, value ?? 0))) + '%' }} />
    </ProgressPrimitive.Root>
));
Progress.displayName = ProgressPrimitive.Root.displayName;
export { Progress };
