<?php

namespace Module\AcademicMobility\Core\Concerns;

/**
 * A `name` and `description` written in one language plus a `translations` map for the others
 * (`{"es": {"name": "...", "description": "..."}}`). The localized accessors follow the app's locale.
 *
 * @property string $name
 * @property string|null $description
 * @property array<string, array<string, string>>|null $translations
 */
trait HasTranslatedTexts
{
    public function localizedName(?string $locale = null): string
    {
        return $this->translated('name', $locale) ?? $this->name;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return $this->translated('description', $locale) ?? $this->description;
    }

    private function translated(string $attribute, ?string $locale): ?string
    {
        $text = $this->translations[$locale ?? app()->getLocale()][$attribute] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }
}
