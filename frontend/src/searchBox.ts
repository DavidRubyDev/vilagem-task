import { nextActive, type PickTarget, type SearchGroup, type SearchOption } from './searchOptions';
import type { Texts } from './texts';

export interface SearchBoxCallbacks {
  onInput(text: string): void;
  onPick(target: PickTarget, name: string): void;
}

type StatusKind = 'loading' | 'noResults' | 'error' | 'outdated';

const STATUS_KEYS = {
  loading: 'search.loading',
  noResults: 'search.noResults',
  error: 'search.error',
  outdated: 'search.outdated',
} as const;

const LIST_ID = 'map-search-list';
const ACTIVE_CLASS = 'map-search-option-active';

/**
 * The search box: an ARIA combobox with a list of results below it. It owns its DOM, appended to the body, and
 * the only listeners are on its own elements. Texts are set with textContent, never as markup.
 */
export class SearchBox {
  private readonly root: HTMLElement;
  private readonly input: HTMLInputElement;
  private readonly list: HTMLElement;
  private readonly status: HTMLElement;
  private options: { readonly option: SearchOption; readonly element: HTMLElement }[] = [];
  private active: number | null = null;
  private statusKind: StatusKind | null = null;

  constructor(
    private texts: Texts,
    private readonly callbacks: SearchBoxCallbacks,
  ) {
    this.root = document.createElement('div');
    this.root.className = 'map-search';
    this.root.setAttribute('role', 'search');

    this.input = document.createElement('input');
    this.input.className = 'map-search-input';
    this.input.type = 'search';
    this.input.setAttribute('role', 'combobox');
    this.input.setAttribute('aria-autocomplete', 'list');
    this.input.setAttribute('aria-expanded', 'false');
    this.input.setAttribute('aria-controls', LIST_ID);
    this.input.autocomplete = 'off';
    this.input.spellcheck = false;
    this.input.enterKeyHint = 'search';

    this.list = document.createElement('div');
    this.list.className = 'map-search-list';
    this.list.id = LIST_ID;
    this.list.setAttribute('role', 'listbox');
    this.list.hidden = true;

    this.status = document.createElement('p');
    this.status.className = 'map-search-status';
    this.status.setAttribute('role', 'status');
    this.status.hidden = true;

    this.root.append(this.input, this.list, this.status);
    this.applyLabels();
    this.listen();
    document.body.append(this.root);
  }

  element(): HTMLElement {
    return this.root;
  }

  /** Shows the groups; no options at all means "nothing found". */
  showGroups(groups: SearchGroup[]): void {
    this.options = [];
    this.active = null;
    this.input.removeAttribute('aria-activedescendant');
    this.list.replaceChildren();
    for (const group of groups) {
      this.list.append(this.renderGroup(group));
    }
    if (this.options.length === 0) {
      this.setList(false);
      this.showStatus('noResults');
      return;
    }
    this.hideStatus();
    this.setList(true);
  }

  /** The results shown so far stay while a request is pending; the status only appears when there are none. */
  showSearching(): void {
    if (this.options.length === 0 || this.list.hidden) {
      this.showStatus('loading');
    }
  }

  showError(): void {
    this.dropResults();
    this.showStatus('error');
  }

  showOutdated(): void {
    this.dropResults();
    this.showStatus('outdated');
  }

  close(): void {
    this.setList(false);
    this.hideStatus();
  }

  /** Rewrites the labels, the status and the open list in the language of `texts`; the groups come in `groups`. */
  setTexts(texts: Texts, groups: SearchGroup[] | null = null): void {
    this.texts = texts;
    this.applyLabels();
    if (this.statusKind !== null) {
      this.showStatus(this.statusKind);
    }
    if (groups !== null) {
      this.showGroups(groups);
    }
  }

  private applyLabels(): void {
    const label = this.texts.text('search.label');
    this.root.setAttribute('aria-label', label);
    this.input.setAttribute('aria-label', label);
    this.input.placeholder = this.texts.text('search.placeholder');
    this.list.setAttribute('aria-label', this.texts.text('search.results'));
  }

  private renderGroup(group: SearchGroup): HTMLElement {
    const element = document.createElement('div');
    element.setAttribute('role', 'group');
    const headingId = `map-search-group-${group.key}`;
    element.setAttribute('aria-labelledby', headingId);

    const heading = document.createElement('div');
    heading.className = 'map-search-group';
    heading.id = headingId;
    heading.setAttribute('role', 'presentation');
    heading.textContent = group.label;
    element.append(heading);

    for (const option of group.options) {
      element.append(this.renderOption(option));
    }
    return element;
  }

  private renderOption(option: SearchOption): HTMLElement {
    const element = document.createElement('div');
    element.className = 'map-search-option';
    element.id = option.id;
    element.setAttribute('role', 'option');
    element.setAttribute('aria-selected', 'false');

    const name = document.createElement('span');
    name.className = 'map-search-option-name';
    name.textContent = option.name;
    const context = document.createElement('span');
    context.className = 'map-search-option-context';
    context.textContent = option.context;
    element.append(name, context);

    element.addEventListener('click', () => {
      this.pick(option);
    });
    this.options.push({ option, element });
    return element;
  }

  private listen(): void {
    this.input.addEventListener('input', () => {
      this.callbacks.onInput(this.input.value);
    });
    this.input.addEventListener('keydown', (event) => {
      this.onKeyDown(event);
    });
    // Keeps the focus in the input, so that a click on an option does not blur it before the click arrives.
    this.list.addEventListener('pointerdown', (event) => {
      event.preventDefault();
    });
    this.root.addEventListener('focusout', (event) => {
      const next = event.relatedTarget;
      if (!(next instanceof Node) || !this.root.contains(next)) {
        this.setList(false);
      }
    });
  }

  private onKeyDown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      if (this.options.length === 0) return;
      event.preventDefault();
      this.setList(true);
      this.setActive(nextActive(this.active, this.options.length, event.key === 'ArrowDown' ? 1 : -1));
    } else if (event.key === 'Enter') {
      const chosen = this.options[this.active ?? 0];
      if (this.list.hidden || chosen === undefined) return;
      event.preventDefault();
      this.pick(chosen.option);
    } else if (event.key === 'Escape' && !this.list.hidden) {
      // Closes the list only: the event must not reach the handler that closes the parcel panel.
      event.preventDefault();
      event.stopPropagation();
      this.setList(false);
    }
  }

  private pick(option: SearchOption): void {
    this.input.value = option.name;
    this.close();
    this.input.blur();
    this.callbacks.onPick(option.target, option.name);
  }

  private setActive(index: number | null): void {
    this.active = index;
    for (const [position, { element }] of this.options.entries()) {
      const isActive = position === index;
      element.setAttribute('aria-selected', String(isActive));
      element.classList.toggle(ACTIVE_CLASS, isActive);
      if (isActive) {
        this.input.setAttribute('aria-activedescendant', element.id);
        element.scrollIntoView({ block: 'nearest' });
      }
    }
    if (index === null) {
      this.input.removeAttribute('aria-activedescendant');
    }
  }

  private setList(open: boolean): void {
    const visible = open && this.options.length > 0;
    this.list.hidden = !visible;
    this.input.setAttribute('aria-expanded', String(visible));
    if (!visible) {
      this.setActive(null);
    }
  }

  private dropResults(): void {
    this.options = [];
    this.list.replaceChildren();
    this.setList(false);
  }

  private showStatus(kind: StatusKind): void {
    this.statusKind = kind;
    this.status.textContent = this.texts.text(STATUS_KEYS[kind]);
    this.status.hidden = false;
  }

  private hideStatus(): void {
    this.statusKind = null;
    this.status.textContent = '';
    this.status.hidden = true;
  }
}
