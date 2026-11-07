export type Translations = Record<string, string>;

export type Replacements = Record<string, string | number>;

/**
 * Translate an English text (the key, as in Laravel's JSON translations) with the
 * strings of the current locale; an untranslated text is shown as it is.
 * `:name` placeholders are replaced, like `__('Hello :name', ['name' => ...])`.
 */
export function translate(translations: Translations, key: string, replacements: Replacements = {}): string {
    let text = translations[key] ?? key;

    for (const [name, value] of Object.entries(replacements)) {
        text = text.replaceAll(`:${name}`, String(value));
    }

    return text;
}
