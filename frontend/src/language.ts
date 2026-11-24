export const DEFAULT_LANGUAGE = 'cs';

/** The default language first, then the others alphabetically. */
export function orderLanguages(languages: readonly string[]): string[] {
  const others = languages.filter((language) => language !== DEFAULT_LANGUAGE).sort();
  return languages.includes(DEFAULT_LANGUAGE) ? [DEFAULT_LANGUAGE, ...others] : others;
}

/** The stored language when it is available, otherwise the default. */
export function chooseLanguage(stored: string | null, available: readonly string[]): string {
  return stored !== null && available.includes(stored) ? stored : DEFAULT_LANGUAGE;
}

/** The language's own name, first letter upper-cased in that language: 'Čeština', 'English'. */
export function languageName(language: string): string {
  try {
    const name = new Intl.DisplayNames([language], { type: 'language' }).of(language);
    if (name !== undefined) {
      return name.charAt(0).toLocaleUpperCase(language) + name.slice(1);
    }
  } catch {
    // An unknown code has no name; the upper-cased code below is the readable fallback.
  }
  return language.toUpperCase();
}
