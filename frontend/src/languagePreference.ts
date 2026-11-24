export const LANGUAGE_STORAGE_KEY = 'parcel-map.language';

export interface LanguageStorage {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
}

/** The stored language, or null when nothing is stored or the storage cannot be read (any error). */
export function storedLanguage(storage: () => LanguageStorage): string | null {
  try {
    return storage().getItem(LANGUAGE_STORAGE_KEY);
  } catch {
    // Blocked or unavailable storage (privacy modes) means no stored choice, not a broken page.
    return null;
  }
}

/** Stores the language; a storage error is reported with console.warn and otherwise ignored. */
export function rememberLanguage(storage: () => LanguageStorage, language: string): void {
  try {
    storage().setItem(LANGUAGE_STORAGE_KEY, language);
  } catch (error) {
    console.warn('The chosen language could not be remembered.', error);
  }
}
