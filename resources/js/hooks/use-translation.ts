import { usePage } from '@inertiajs/react';
import { translate } from '@/lib/i18n';
import type { Replacements, Translations } from '@/lib/i18n';

type SharedI18n = {
    locale?: string;
    locales?: string[];
    translations?: Translations | unknown[];
};

/**
 * Texts in the language the server picked for this request (shared by Inertia).
 *
 * @example
 * const { t } = useTranslation();
 * <h1>{t('Users')}</h1>
 * t('Page :page of :last', { page: 1, last: 3 })
 */
export function useTranslation() {
    const { locale = 'en', locales = ['en'], translations } = usePage<SharedI18n>().props;
    // Laravel encodes an empty array (no translations in English) as `[]`.
    const dictionary: Translations = Array.isArray(translations) ? {} : (translations ?? {});

    return {
        locale,
        locales,
        t: (key: string, replacements?: Replacements) => translate(dictionary, key, replacements),
    };
}
