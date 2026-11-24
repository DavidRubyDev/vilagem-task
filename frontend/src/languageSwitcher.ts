import type { IControl } from 'maplibre-gl';
import type { Texts } from './texts';

export interface LanguageOption {
  readonly code: string;
  /** The language's own name, shown on hover and read by screen readers. */
  readonly name: string;
  readonly flagUrl: string;
}

/** Flag buttons above the legend. Only listener: a click on a button. */
export class LanguageSwitcherControl implements IControl {
  private root: HTMLElement | null = null;
  private readonly buttons = new Map<string, HTMLButtonElement>();

  constructor(
    private readonly options: readonly LanguageOption[],
    private texts: Texts,
    private readonly onSelect: (code: string) => void,
  ) {}

  onAdd(): HTMLElement {
    const group = document.createElement('div');
    group.className = 'maplibregl-ctrl map-language';
    group.setAttribute('role', 'group');

    for (const { code, name, flagUrl } of this.options) {
      const flag = document.createElement('img');
      flag.src = flagUrl;
      flag.alt = '';
      flag.width = 24;
      flag.height = 16;
      const button = document.createElement('button');
      button.type = 'button';
      button.lang = code;
      button.setAttribute('aria-label', name);
      button.title = name;
      button.addEventListener('click', () => {
        this.onSelect(code);
      });
      button.append(flag);
      group.append(button);
      this.buttons.set(code, button);
    }

    this.root = group;
    this.setTexts(this.texts);
    return group;
  }

  onRemove(): void {
    this.root?.remove();
    this.root = null;
    this.buttons.clear();
  }

  setTexts(texts: Texts): void {
    this.texts = texts;
    this.root?.setAttribute('aria-label', texts.text('language.label'));
    for (const [code, button] of this.buttons) {
      button.setAttribute('aria-pressed', String(code === texts.language));
    }
  }
}
