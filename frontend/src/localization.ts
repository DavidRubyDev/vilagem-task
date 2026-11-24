import type { Texts } from './texts';

/**
 * The current interface texts and the languages loaded so far. A switch tells the listeners, which rewrite their
 * texts in place; nothing here knows the map or the DOM.
 */
export class Localization {
  private current_: Texts;
  private requested: string;
  private readonly cache = new Map<string, Texts>();
  private readonly listeners: ((texts: Texts) => void)[] = [];

  constructor(
    initial: Texts,
    private readonly load: (language: string) => Promise<Texts>,
  ) {
    this.current_ = initial;
    this.requested = initial.language;
    this.cache.set(initial.language, initial);
  }

  current(): Texts {
    return this.current_;
  }

  onChange(listener: (texts: Texts) => void): void {
    this.listeners.push(listener);
  }

  /** Never rejects: a failed load keeps the current language and is reported with console.warn. */
  async switchTo(language: string): Promise<void> {
    // The last request wins: a load that is still pending when another language is picked is cached, not applied.
    this.requested = language;
    const cached = this.cache.get(language);
    if (cached !== undefined) {
      this.apply(cached);
      return;
    }
    let texts: Texts;
    try {
      texts = await this.load(language);
    } catch (error) {
      console.warn('The texts could not be loaded.', error);
      return;
    }
    this.cache.set(language, texts);
    if (this.requested === language) {
      this.apply(texts);
    }
  }

  private apply(texts: Texts): void {
    if (texts.language === this.current_.language) return;
    this.current_ = texts;
    for (const listener of this.listeners) {
      listener(texts);
    }
  }
}
