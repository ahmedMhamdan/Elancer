import { useTranslation } from '@/hooks/use-translation';
// Adapted from local TailAdmin src/components/form/MultiSelect.tsx (MIT).
// Preserves controlled selections, removable pills, relative wrapper and option rows.
// Adds database search, debouncing/cancellation, input combobox and keyboard support.
import { useEffect, useRef, useState } from 'react';
import { X } from 'lucide-react';
import Input from '@/components/tailadmin/input';
type Skill = { id: number; name: string };
export default function SkillSelect({
    id,
    value,
    onChange,
    error,
    disabled = false,
}: {
    id: string;
    value: string[];
    onChange: (value: string[]) => void;
    error?: string;
    disabled?: boolean;
}) {
    const { t } = useTranslation();

    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<Skill[]>([]);
    const [open, setOpen] = useState(false);
    const [focusedIndex, setFocusedIndex] = useState(-1);
    const [loading, setLoading] = useState(false);
    const [failure, setFailure] = useState('');
    const input = useRef<HTMLInputElement>(null);
    const root = useRef<HTMLDivElement>(null);
    const available = options.filter((option) => !value.includes(option.name));
    useEffect(() => {
        if (!open || disabled) return;
        const controller = new AbortController();
        setLoading(true);
        setFailure('');
        setOptions([]);
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    '/skills?q=' + encodeURIComponent(query),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                if (!response.ok) throw new Error('request');
                const result: { data: Skill[] } = await response.json();
                if (!controller.signal.aborted) {
                    setOptions(result.data);
                    setFocusedIndex(-1);
                }
            } catch {
                if (!controller.signal.aborted)
                    setFailure(
                        'Could not load skills. Close and reopen to retry.',
                    );
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 200);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, open, disabled]);
    useEffect(() => {
        if (!open) return;
        function outside(event: PointerEvent) {
            if (!root.current?.contains(event.target as Node)) setOpen(false);
        }
        document.addEventListener('pointerdown', outside);
        return () => document.removeEventListener('pointerdown', outside);
    }, [open]);
    useEffect(() => {
        if (focusedIndex >= 0)
            document
                .getElementById(id + '-option-' + focusedIndex)
                ?.scrollIntoView({ block: 'nearest' });
    }, [focusedIndex, id]);
    function select(name: string) {
        if (disabled || value.length >= 15 || value.includes(name)) return;
        onChange([...value, name]);
        setQuery('');
        setFocusedIndex(-1);
        setOpen(false);
        input.current?.focus();
    }
    return (
        <div
            ref={root}
            className="w-full"
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget))
                    setOpen(false);
            }}
        >
            <div className="relative w-full">
                <div className="border-input bg-background mb-2 flex min-h-11 flex-wrap items-center gap-2 rounded-lg border p-2">
                    {value.map((name) => (
                        <div
                            key={name}
                            className="bg-primary/10 text-foreground flex max-w-full items-center rounded-full ps-3 text-sm"
                        >
                            <span dir="auto" className="break-words">
                                {name}
                            </span>
                            <button
                                type="button"
                                disabled={disabled}
                                className="focus-visible:outline-ring hover:bg-primary/15 flex size-11 shrink-0 items-center justify-center rounded-full focus-visible:outline-2"
                                aria-label={t('Remove :name', { name })}
                                onClick={() => {
                                    onChange(
                                        value.filter((item) => item !== name),
                                    );
                                    input.current?.focus();
                                }}
                            >
                                <X size={14} aria-hidden="true" />
                            </button>
                        </div>
                    ))}
                    <Input
                        ref={input}
                        id={id}
                        role="combobox"
                        autoComplete="off"
                        value={query}
                        maxLength={50}
                        placeholder={
                            value.length >= 15
                                ? t('15 skills selected')
                                : t('Type a skill, e.g. Laravel')
                        }
                        disabled={disabled || value.length >= 15}
                        aria-expanded={open}
                        aria-controls={id + '-list'}
                        aria-autocomplete="list"
                        aria-activedescendant={
                            open && focusedIndex >= 0 && available[focusedIndex]
                                ? id + '-option-' + focusedIndex
                                : undefined
                        }
                        aria-invalid={!!error}
                        aria-describedby={
                            id + '-hint' + (error ? ' ' + id + '-error' : '')
                        }
                        onFocus={() => setOpen(true)}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setOptions([]);
                            setFocusedIndex(-1);
                            setOpen(true);
                        }}
                        onKeyDown={(event) => {
                            if (
                                event.key === 'ArrowDown' ||
                                event.key === 'ArrowUp'
                            ) {
                                event.preventDefault();
                                setOpen(true);
                                setFocusedIndex((index) =>
                                    available.length
                                        ? (index +
                                              (event.key === 'ArrowDown'
                                                  ? 1
                                                  : available.length - 1) +
                                              available.length) %
                                          available.length
                                        : -1,
                                );
                            } else if (event.key === 'Enter') {
                                event.preventDefault();
                                if (open && available[focusedIndex])
                                    select(available[focusedIndex].name);
                            } else if (event.key === 'Escape') {
                                event.preventDefault();
                                setOpen(false);
                            }
                        }}
                    />
                </div>
                {open && !disabled && value.length < 15 && (
                    <div className="bg-card border-border absolute start-0 top-full z-40 max-h-60 w-full overflow-y-auto rounded-lg border shadow-lg">
                        <div
                            id={id + '-list'}
                            role="listbox"
                            aria-label={t('Skill suggestions')}
                            aria-busy={loading}
                        >
                            {available.map((option, index) => (
                                <div
                                    key={option.id}
                                    id={id + '-option-' + index}
                                    role="option"
                                    aria-selected={focusedIndex === index}
                                    className={
                                        'border-border hover:bg-primary/10 min-h-11 cursor-pointer border-b px-4 py-3 text-sm ' +
                                        (focusedIndex === index
                                            ? 'bg-primary/10'
                                            : '')
                                    }
                                    onPointerDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => select(option.name)}
                                >
                                    {option.name}
                                </div>
                            ))}
                        </div>
                        {(loading || failure || !available.length) && (
                            <p
                                role="status"
                                className="text-muted-foreground p-4 text-sm"
                            >
                                {loading
                                    ? t('Searching skills…')
                                    : t(
                                          failure ||
                                              'No matching skills. Try another word.',
                                      )}
                            </p>
                        )}
                    </div>
                )}
            </div>
            <p id={id + '-hint'} className="text-muted-foreground mt-2 text-sm">
                {t('Type to search, then choose a suggestion.')}{' '}
                {t(':count/15 selected.', { count: value.length })}
            </p>
            {error && (
                <p
                    id={id + '-error'}
                    role="alert"
                    className="text-destructive mt-2 text-sm"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
