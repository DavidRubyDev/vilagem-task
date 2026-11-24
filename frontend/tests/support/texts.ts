import { parseTexts, type Texts } from '../../src/texts';

const FILES = import.meta.glob<Record<string, string>>('../../languages/*.json', { eager: true, import: 'default' });

/** The committed language files by language code, parsed. */
export function languageFiles(): Record<string, Record<string, string>> {
  const files: Record<string, Record<string, string>> = {};
  for (const [path, entries] of Object.entries(FILES)) {
    const code = /([^/]+)\.json$/.exec(path)?.[1];
    if (code === undefined) throw new Error(`Unexpected language file path ${path}.`);
    files[code] = entries;
  }
  return files;
}

export function textsOf(language: 'cs' | 'en'): Texts {
  const entries = languageFiles()[language];
  if (entries === undefined) throw new Error(`No language file for ${language}.`);
  return parseTexts(language, entries);
}
