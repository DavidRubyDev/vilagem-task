import type csTexts from '../languages/cs.json';

export type TextKey = keyof typeof csTexts;

export class TextsError extends Error {}

/** The interface texts of one language. */
export class Texts {
  private readonly numberFormat: Intl.NumberFormat;

  constructor(
    readonly language: string,
    private readonly entries: Readonly<Record<string, string>>,
  ) {
    this.numberFormat = new Intl.NumberFormat(language);
  }

  /** The text of a key with its {placeholders} replaced; a key without a text renders as the key itself. */
  text(key: TextKey, values: Readonly<Record<string, string>> = {}): string {
    return this.lookup(key, values);
  }

  landTypeName(code: number): string {
    const key = `landType.${String(code)}`;
    return key in this.entries ? this.lookup(key) : this.lookup('landType.unknown', { code: String(code) });
  }

  landUseName(code: number): string {
    const key = `landUse.${String(code)}`;
    return key in this.entries ? this.lookup(key) : this.lookup('landUse.unknown', { code: String(code) });
  }

  formatNumber(value: number): string {
    return this.numberFormat.format(value);
  }

  private lookup(key: string, values: Readonly<Record<string, string>> = {}): string {
    const template = this.entries[key];
    if (template === undefined) return key;
    return template.replace(/\{(\w+)\}/g, (placeholder, name: string) => values[name] ?? placeholder);
  }
}

function isFlatObjectOfStrings(body: unknown): body is Record<string, string> {
  if (typeof body !== 'object' || body === null || Array.isArray(body)) return false;
  const values = Object.values(body);
  return values.length > 0 && values.every((value) => typeof value === 'string');
}

/** Throws TextsError unless body is a non-empty plain object whose values are all strings. */
export function parseTexts(language: string, body: unknown): Texts {
  if (!isFlatObjectOfStrings(body)) {
    throw new TextsError(`The texts of language ${language} are not a non-empty flat object of strings.`);
  }
  return new Texts(language, body);
}

/** Throws TextsError when the request fails, answers a non-2xx status, or the body is not valid JSON or not texts. */
export async function loadTexts(fetchFn: typeof fetch, url: string, language: string): Promise<Texts> {
  let response: Response;
  try {
    response = await fetchFn(url, { headers: { Accept: 'application/json' } });
  } catch (error) {
    throw new TextsError(`The texts of language ${language} could not be requested: ${String(error)}`, {
      cause: error,
    });
  }
  if (!response.ok) {
    throw new TextsError(`The texts request of language ${language} failed with status ${String(response.status)}.`);
  }
  let body: unknown;
  try {
    body = await response.json();
  } catch (error) {
    throw new TextsError(`The texts of language ${language} are not valid JSON.`, { cause: error });
  }
  return parseTexts(language, body);
}
