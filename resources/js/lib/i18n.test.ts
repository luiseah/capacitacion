import { describe, expect, it } from 'vitest';
import { translate } from './i18n';

describe('translate', () => {
    it('returns the translation, or the English text when there is none', () => {
        expect(translate({ Users: 'Usuarios' }, 'Users')).toBe('Usuarios');
        expect(translate({ Users: 'Usuarios' }, 'Roles')).toBe('Roles');
        expect(translate({}, 'Users')).toBe('Users');
    });

    it('replaces :placeholders in both', () => {
        expect(translate({ 'Page :page of :last': 'Página :page de :last' }, 'Page :page of :last', { page: 2, last: 5 })).toBe('Página 2 de 5');
        expect(translate({}, 'Delete :name?', { name: 'Ana' })).toBe('Delete Ana?');
    });
});
