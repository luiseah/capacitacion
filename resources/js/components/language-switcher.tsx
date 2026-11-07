import { router } from '@inertiajs/react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';

const LABELS: Record<string, string> = { en: 'EN', es: 'ES' };

/**
 * Switches the interface language: the server remembers it in a cookie and answers the
 * visit with the new translations, so the page re-renders in the chosen language.
 */
export function LanguageSwitcher() {
    const { locale, locales, t } = useTranslation();

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            size="sm"
            value={locale}
            onValueChange={(value) => {
                if (value && value !== locale) {
                    router.post('/locale', { locale: value }, { preserveScroll: true, preserveState: true });
                }
            }}
            aria-label={t('Language')}
        >
            {locales.map((value) => (
                <ToggleGroupItem key={value} value={value} className="px-2 text-xs" aria-label={value === 'es' ? 'Español' : 'English'}>
                    {LABELS[value] ?? value.toUpperCase()}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
