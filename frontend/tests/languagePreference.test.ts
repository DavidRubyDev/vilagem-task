import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  LANGUAGE_STORAGE_KEY,
  rememberLanguage,
  storedLanguage,
  type LanguageStorage,
} from '../src/languagePreference';

function memoryStorage(initial: Record<string, string> = {}): LanguageStorage & { values: Map<string, string> } {
  const values = new Map(Object.entries(initial));
  return {
    values,
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => {
      values.set(key, value);
    },
  };
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('storedLanguage', () => {
  it('returns the stored value', () => {
    const storage = memoryStorage({ 'parcel-map.language': 'en' });

    expect(storedLanguage(() => storage)).toBe('en');
  });

  it('returns null when nothing is stored', () => {
    expect(storedLanguage(() => memoryStorage())).toBeNull();
  });

  it('returns null when the storage cannot even be reached', () => {
    expect(
      storedLanguage(() => {
        throw new DOMException('blocked', 'SecurityError');
      }),
    ).toBeNull();
  });

  it('returns null when reading throws', () => {
    const storage: LanguageStorage = {
      getItem: () => {
        throw new Error('denied');
      },
      setItem: () => undefined,
    };

    expect(storedLanguage(() => storage)).toBeNull();
  });
});

describe('rememberLanguage', () => {
  it('writes the language under the preference key', () => {
    const storage = memoryStorage();

    rememberLanguage(() => storage, 'en');

    expect(LANGUAGE_STORAGE_KEY).toBe('parcel-map.language');
    expect(storage.values.get('parcel-map.language')).toBe('en');
  });

  it('warns instead of throwing when the storage cannot be reached', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

    expect(() => {
      rememberLanguage(() => {
        throw new DOMException('blocked', 'SecurityError');
      }, 'en');
    }).not.toThrow();
    expect(warn).toHaveBeenCalledOnce();
  });

  it('warns instead of throwing when writing fails', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const storage: LanguageStorage = {
      getItem: () => null,
      setItem: () => {
        throw new Error('quota');
      },
    };

    expect(() => {
      rememberLanguage(() => storage, 'en');
    }).not.toThrow();
    expect(warn).toHaveBeenCalledOnce();
  });
});
