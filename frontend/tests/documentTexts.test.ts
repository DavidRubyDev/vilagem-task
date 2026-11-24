import { describe, expect, it } from 'vitest';
import { applyDocumentTexts } from '../src/documentTexts';
import { textsOf } from './support/texts';

describe('applyDocumentTexts', () => {
  it.each([
    ['cs', 'Mapa parcel okresu Jičín'],
    ['en', 'Jičín Parcel Map'],
  ] as const)('sets the language and the title for %s', (language, title) => {
    const target = { documentElement: { lang: '' }, title: '' };

    applyDocumentTexts(target, textsOf(language));

    expect(target.documentElement.lang).toBe(language);
    expect(target.title).toBe(title);
  });
});
