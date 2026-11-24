import type { Texts } from './texts';

export function applyDocumentTexts(target: { documentElement: { lang: string }; title: string }, texts: Texts): void {
  target.documentElement.lang = texts.language;
  target.title = texts.text('page.title');
}
