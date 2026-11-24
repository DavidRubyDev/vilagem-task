import { orderLanguages } from './language';

// no-inline: Vite would inline files under 4 KB as data: URLs, which would put the texts into the JS bundle.
const TEXT_FILES = import.meta.glob<string>('../languages/*.json', {
  query: '?url&no-inline',
  import: 'default',
  eager: true,
});

const FLAG_FILES = import.meta.glob<string>('../languages/*.svg', {
  query: '?url&no-inline',
  import: 'default',
  eager: true,
});

export class UnknownLanguage extends Error {}

function urlsByLanguage(files: Readonly<Record<string, string>>): ReadonlyMap<string, string> {
  const urls = new Map<string, string>();
  for (const [path, url] of Object.entries(files)) {
    const code = /([^/]+)\.[a-z]+$/.exec(path)?.[1];
    if (code !== undefined) urls.set(code, url);
  }
  return urls;
}

const TEXTS_URLS = urlsByLanguage(TEXT_FILES);
const FLAG_URLS = urlsByLanguage(FLAG_FILES);

/** Every language with a texts file, ordered by orderLanguages. */
export function availableLanguages(): readonly string[] {
  return orderLanguages([...TEXTS_URLS.keys()]);
}

/** The URL of the language's texts; throws UnknownLanguage for a language without a texts file. */
export function textsUrl(language: string): string {
  const url = TEXTS_URLS.get(language);
  if (url === undefined) {
    throw new UnknownLanguage(`There is no texts file for language ${language}.`);
  }
  return url;
}

/** The URL of the language's flag; throws UnknownLanguage for a language without a flag file. */
export function flagUrl(language: string): string {
  const url = FLAG_URLS.get(language);
  if (url === undefined) {
    throw new UnknownLanguage(`There is no flag file for language ${language}.`);
  }
  return url;
}
