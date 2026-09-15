import { useTranslation } from '@/hooks/use-translation';
// No crop editor exists in the inspected local TailAdmin source (2026-09-13).
// Elancer extension using the adapted Modal and Button; browser canvas crops locally.
import { useEffect, useRef, useState } from 'react';
import { RotateCw } from 'lucide-react';
import Modal from '@/components/tailadmin/modal';
import Button from '@/components/tailadmin/button';
import Label from '@/components/tailadmin/label';
const SIZE = 512;
const clamp = (n: number) => Math.max(-100, Math.min(100, n));
export default function PhotoEditor({
    file,
    onApply,
    onClose,
}: {
    file: File;
    onApply: (photo: File) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation();

    const canvas = useRef<HTMLCanvasElement>(null);
    const [image, setImage] = useState<HTMLImageElement>();
    const [zoom, setZoom] = useState(1);
    const [rotation, setRotation] = useState(0);
    const [x, setX] = useState(0);
    const [y, setY] = useState(0);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const drag = useRef<{
        x: number;
        y: number;
        startX: number;
        startY: number;
    } | null>(null);
    const alive = useRef(true);
    useEffect(() => {
        alive.current = true;
        const url = URL.createObjectURL(file);
        const img = new Image();
        let cancelled = false;
        img.onload = () => {
            if (cancelled) return;
            if (img.naturalWidth > 6000 || img.naturalHeight > 6000) {
                setError('Choose an image no larger than 6000 × 6000 pixels.');
                return;
            }
            setImage(img);
        };
        img.onerror = () => {
            if (!cancelled)
                setError(
                    'This image could not be opened. Try a different photo.',
                );
        };
        img.src = url;
        return () => {
            cancelled = true;
            alive.current = false;
            URL.revokeObjectURL(url);
        };
    }, [file]);
    const width = image
        ? rotation % 180
            ? image.naturalHeight
            : image.naturalWidth
        : SIZE;
    const height = image
        ? rotation % 180
            ? image.naturalWidth
            : image.naturalHeight
        : SIZE;
    const scale = Math.max(SIZE / width, SIZE / height) * zoom;
    const excessX = Math.max(0, width * scale - SIZE) / 2;
    const excessY = Math.max(0, height * scale - SIZE) / 2;
    useEffect(() => {
        const ctx = canvas.current?.getContext('2d');
        if (!ctx || !image) return;
        ctx.clearRect(0, 0, SIZE, SIZE);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, SIZE, SIZE);
        ctx.save();
        ctx.translate(
            SIZE / 2 + (x / 100) * excessX,
            SIZE / 2 + (y / 100) * excessY,
        );
        ctx.rotate((rotation * Math.PI) / 180);
        ctx.scale(scale, scale);
        ctx.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2);
        ctx.restore();
    }, [image, scale, rotation, x, y, excessX, excessY]);
    function apply() {
        if (!canvas.current || !image || saving) return;
        setSaving(true);
        canvas.current.toBlob(
            (blob) => {
                if (!alive.current) return;
                setSaving(false);
                if (!blob || blob.size > 2 * 1024 * 1024) {
                    setError('Could not prepare the photo. Try another image.');
                    return;
                }
                onApply(
                    new File([blob], 'profile-photo.jpg', {
                        type: 'image/jpeg',
                    }),
                );
            },
            'image/jpeg',
            0.9,
        );
    }
    return (
        <Modal
            open
            onClose={() => {
                if (!saving) onClose();
            }}
            title={t('Make your photo fit')}
            description={t(
                'Drag to reposition, or use the sliders. The circle shows how your profile photo will appear.',
            )}
        >
            <div className="mx-auto max-w-80 space-y-4">
                <div className="bg-muted relative aspect-square overflow-hidden rounded-xl">
                    <canvas
                        ref={canvas}
                        width={SIZE}
                        height={SIZE}
                        aria-label={t('Profile photo crop preview')}
                        className="h-full w-full cursor-move touch-none"
                        onPointerDown={(event) => {
                            if (!image || saving) return;
                            event.currentTarget.setPointerCapture(
                                event.pointerId,
                            );
                            drag.current = {
                                x: event.clientX,
                                y: event.clientY,
                                startX: x,
                                startY: y,
                            };
                        }}
                        onPointerMove={(event) => {
                            if (!drag.current || saving) return;
                            const ratio =
                                SIZE /
                                event.currentTarget.getBoundingClientRect()
                                    .width;
                            if (excessX > 0)
                                setX(
                                    clamp(
                                        drag.current.startX +
                                            (((event.clientX - drag.current.x) *
                                                ratio) /
                                                excessX) *
                                                100,
                                    ),
                                );
                            if (excessY > 0)
                                setY(
                                    clamp(
                                        drag.current.startY +
                                            (((event.clientY - drag.current.y) *
                                                ratio) /
                                                excessY) *
                                                100,
                                    ),
                                );
                        }}
                        onPointerUp={() => {
                            drag.current = null;
                        }}
                        onPointerCancel={() => {
                            drag.current = null;
                        }}
                    />
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 rounded-full border-2 border-white/90 shadow-[0_0_0_100px_rgba(0,0,0,0.4)]"
                    />
                </div>
                {!image && !error && <p role="status">{t('Opening photo…')}</p>}
                {error && (
                    <p role="alert" className="text-destructive text-sm">
                        {t(error)}
                    </p>
                )}
                <fieldset disabled={!image || saving} className="space-y-3">
                    <legend className="sr-only">
                        {t('Photo adjustments')}
                    </legend>
                    <div>
                        <Label htmlFor="photo-zoom">{t('Zoom')}</Label>
                        <input
                            id="photo-zoom"
                            type="range"
                            min="1"
                            max="3"
                            step="0.01"
                            value={zoom}
                            onChange={(e) => setZoom(Number(e.target.value))}
                            className="accent-primary min-h-11 w-full"
                        />
                    </div>
                    <div>
                        <Label htmlFor="photo-x">
                            {t('Horizontal position')}
                        </Label>
                        <input
                            id="photo-x"
                            type="range"
                            min="-100"
                            max="100"
                            value={x}
                            disabled={!excessX}
                            onChange={(e) => setX(Number(e.target.value))}
                            className="accent-primary min-h-11 w-full"
                        />
                    </div>
                    <div>
                        <Label htmlFor="photo-y">
                            {t('Vertical position')}
                        </Label>
                        <input
                            id="photo-y"
                            type="range"
                            min="-100"
                            max="100"
                            value={y}
                            disabled={!excessY}
                            onChange={(e) => setY(Number(e.target.value))}
                            className="accent-primary min-h-11 w-full"
                        />
                    </div>
                    <div className="flex justify-between gap-2">
                        <Button
                            variant="outline"
                            onClick={() => {
                                setRotation((rotation + 90) % 360);
                                setX(0);
                                setY(0);
                            }}
                        >
                            <RotateCw size={16} aria-hidden="true" />
                            {t('Rotate')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setZoom(1);
                                setRotation(0);
                                setX(0);
                                setY(0);
                            }}
                        >
                            {t('Reset')}
                        </Button>
                    </div>
                </fieldset>
            </div>
            <div className="mt-6 flex justify-end gap-3">
                <Button variant="outline" disabled={saving} onClick={onClose}>
                    {t('Cancel')}
                </Button>
                <Button disabled={!image || saving} onClick={apply}>
                    {saving ? t('Preparing…') : t('Apply photo')}
                </Button>
            </div>
        </Modal>
    );
}
