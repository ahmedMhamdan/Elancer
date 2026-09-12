// Adapted from TailAdmin src/components/form/form-elements/DropZone.tsx (MIT),
// retrieved 2026-09-12. Retains dashed wrapper, drop target, icon, instructions
// and browse affordance. Compact layout, Elancer tokens and native file handling
// replace react-dropzone; button replaces nested form for valid form semantics.
import { FileImage, UploadCloud, CheckCircle2 } from 'lucide-react';
import { useRef, useState } from 'react';
import Label from '@/components/tailadmin/label';
import InputError from '@/components/input-error';
export default function IdentityUpload({
    id,
    label,
    file,
    onChange,
    error,
    disabled = false,
}: {
    id: string;
    label: string;
    file: File | null;
    onChange: (file: File | null) => void;
    error?: string;
    disabled?: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState('');
    function choose(files: FileList | null) {
        setDragging(false);
        if (disabled || !files?.length) return;
        const selected = files[0];
        if (
            files.length !== 1 ||
            !['image/jpeg', 'image/png'].includes(selected.type) ||
            selected.size > 2 * 1024 * 1024
        ) {
            onChange(null);
            setLocalError('Choose one JPEG or PNG image, up to 2 MB.');
            if (input.current) input.current.value = '';
            return;
        }
        setLocalError('');
        onChange(selected);
    }
    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <input
                ref={input}
                id={id}
                type="file"
                accept="image/jpeg,image/png"
                className="sr-only"
                tabIndex={-1}
                disabled={disabled}
                onChange={(event) => choose(event.target.files)}
            />
            <div
                className={`rounded-xl border border-dashed transition-colors ${dragging ? 'border-primary bg-muted' : 'border-input bg-muted/30 hover:border-primary'}`}
            >
                <button
                    type="button"
                    disabled={disabled}
                    aria-label={`${file ? 'Change' : 'Choose'} ${label.toLowerCase()}`}
                    aria-describedby={`${id}-help ${id}-error`}
                    aria-invalid={!!(localError || error)}
                    onClick={() => input.current?.click()}
                    onDragOver={(event) => {
                        event.preventDefault();
                        if (!disabled) setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        choose(event.dataTransfer.files);
                    }}
                    className="focus-visible:outline-ring flex w-full cursor-pointer items-center gap-4 rounded-xl p-4 text-start focus-visible:outline-2 disabled:cursor-wait disabled:opacity-60"
                >
                    <span className="bg-muted text-primary grid size-10 shrink-0 place-items-center rounded-full">
                        {file ? (
                            <CheckCircle2 size={21} aria-hidden="true" />
                        ) : dragging ? (
                            <UploadCloud size={21} aria-hidden="true" />
                        ) : (
                            <FileImage size={21} aria-hidden="true" />
                        )}
                    </span>
                    <span className="min-w-0">
                        <span className="block text-sm font-medium">
                            {dragging
                                ? 'Drop your image here'
                                : file
                                  ? 'Image selected'
                                  : `Choose ${label.toLowerCase()}`}
                        </span>
                        <span
                            id={`${id}-help`}
                            className="text-muted-foreground mt-1 block text-xs"
                        >
                            Drag a photo here or click to browse · JPEG / PNG ·
                            Max 2 MB
                        </span>
                        <span className="text-primary mt-2 block text-xs font-medium underline">
                            {file ? 'Change file' : 'Browse file'}
                        </span>
                    </span>
                </button>
            </div>
            {file && (
                <div className="mt-2 flex items-center justify-between gap-3">
                    <p
                        role="status"
                        className="text-muted-foreground min-w-0 text-xs break-all"
                    >
                        {file.name} · {(file.size / 1024 / 1024).toFixed(2)} MB
                    </p>
                    <button
                        type="button"
                        disabled={disabled}
                        className="text-primary min-h-11 shrink-0 px-2 text-xs underline"
                        onClick={() => {
                            onChange(null);
                            setLocalError('');
                            if (input.current) input.current.value = '';
                        }}
                    >
                        Remove
                    </button>
                </div>
            )}
            <InputError id={`${id}-error`} message={localError || error} />
        </div>
    );
}
