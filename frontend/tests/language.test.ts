import { describe, expect, it } from 'vitest';
import { chooseLanguage, DEFAULT_LANGUAGE, languageName, orderLanguages } from '../src/language';

describe('orderLanguages', () => {
  it('puts the default language first', () => {
    expect(orderLanguages(['en', 'cs'])).toEqual(['cs', 'en']);
  });

  it('orders the others alphabetically', () => {
    expect(orderLanguages(['en', 'de', 'cs'])).toEqual(['cs', 'de', 'en']);
  });
});

describe('chooseLanguage', () => {
  it.each([null, '', 'de', 'EN'])('gives the default language for %j', (stored) => {
    expect(chooseLanguage(stored, ['cs', 'en'])).toBe(DEFAULT_LANGUAGE);
  });

  it('gives an available stored language', () => {
    expect(chooseLanguage('en', ['cs', 'en'])).toBe('en');
  });
});

describe('languageName', () => {
  it('names a language in itself with a capital first letter', () => {
    expect(languageName('cs')).toBe('Čeština');
    expect(languageName('en')).toBe('English');
  });
});
