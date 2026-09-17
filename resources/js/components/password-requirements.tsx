import { useTranslation } from '@/hooks/use-translation';
export default function PasswordRequirements() {
    const { t } = useTranslation();
    return (
        <p className="text-muted-foreground text-sm">
            {t(
                'Use at least 12 characters, including uppercase and lowercase letters, a number, and a symbol.',
            )}
        </p>
    );
}
