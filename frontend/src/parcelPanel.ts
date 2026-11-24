import type { ParcelDetail } from './parcelDetail';
import { formatArea, formatCadastralArea, plotTypeName } from './parcelFormat';
import type { Texts } from './texts';

type Shown =
  { readonly kind: 'loading' | 'notFound' | 'error' } | { readonly kind: 'detail'; readonly detail: ParcelDetail };
type Content = { readonly kind: 'none' } | Shown;

const MESSAGE_KEYS = { loading: 'panel.loading', notFound: 'panel.notFound', error: 'panel.error' } as const;

/** The side panel with the details of the selected parcel. It overlays the map and never resizes it. */
export class ParcelPanel {
  private readonly root: HTMLElement;
  private readonly closeButton: HTMLButtonElement;
  private readonly title: HTMLElement;
  private readonly facts: HTMLElement;
  private readonly viewerLink: HTMLAnchorElement;
  private readonly message: HTMLElement;
  // Kept so that a language switch can render the same content again.
  private content: Content = { kind: 'none' };

  constructor(
    private texts: Texts,
    onClose: () => void,
    private readonly onVisibilityChange: () => void,
  ) {
    this.root = document.createElement('aside');
    this.root.className = 'parcel-panel';
    this.root.hidden = true;

    this.closeButton = document.createElement('button');
    this.closeButton.type = 'button';
    this.closeButton.className = 'parcel-panel-close';
    this.closeButton.textContent = '×';
    this.closeButton.addEventListener('click', onClose);

    this.title = document.createElement('h2');
    this.title.className = 'parcel-panel-title';
    this.facts = document.createElement('dl');
    this.facts.className = 'parcel-panel-facts';
    this.viewerLink = document.createElement('a');
    this.viewerLink.className = 'parcel-panel-viewer';
    this.viewerLink.target = '_blank';
    this.viewerLink.rel = 'noopener noreferrer';
    this.viewerLink.hidden = true;
    this.message = document.createElement('p');
    this.message.className = 'parcel-panel-message';
    this.message.setAttribute('role', 'status');

    this.root.append(this.closeButton, this.title, this.facts, this.viewerLink, this.message);
    this.applyLabels();
    document.body.append(this.root);
  }

  element(): HTMLElement {
    return this.root;
  }

  isOpen(): boolean {
    return !this.root.hidden;
  }

  showLoading(): void {
    this.show({ kind: 'loading' });
  }

  showNotFound(): void {
    this.show({ kind: 'notFound' });
  }

  showError(): void {
    this.show({ kind: 'error' });
  }

  showDetail(detail: ParcelDetail): void {
    this.show({ kind: 'detail', detail });
  }

  hide(): void {
    this.content = { kind: 'none' };
    this.setHidden(true);
  }

  /** Rewrites the texts of whatever the panel shows. It never opens or closes the panel. */
  setTexts(texts: Texts): void {
    this.texts = texts;
    this.applyLabels();
    const { content } = this;
    if (content.kind !== 'none') {
      this.render(content);
    }
  }

  private show(content: Shown): void {
    this.content = content;
    this.render(content);
    this.setHidden(false);
  }

  private render(content: Shown): void {
    if (content.kind === 'detail') {
      this.renderDetail(content.detail);
    } else {
      this.renderMessage(this.texts.text(MESSAGE_KEYS[content.kind]));
    }
  }

  private applyLabels(): void {
    this.root.setAttribute('aria-label', this.texts.text('panel.label'));
    this.closeButton.setAttribute('aria-label', this.texts.text('panel.close'));
    this.viewerLink.textContent = this.texts.text('panel.viewerLink');
  }

  private renderDetail(detail: ParcelDetail): void {
    this.title.textContent = this.texts.text('panel.heading', { parcelNumber: detail.parcelNumber });
    this.title.hidden = false;
    this.message.textContent = '';
    this.facts.replaceChildren();
    this.addFact(
      this.texts.text('panel.cadastralArea'),
      formatCadastralArea(detail.cadastralArea.name, detail.cadastralArea.code),
    );
    this.addFact(this.texts.text('panel.municipality'), detail.municipality.name);
    this.addFact(this.texts.text('panel.plotType'), plotTypeName(detail.kind, this.texts));
    this.addFact(this.texts.text('panel.landType'), this.texts.landTypeName(detail.landTypeCode));
    if (detail.landUseCode !== null) {
      this.addFact(this.texts.text('panel.landUse'), this.texts.landUseName(detail.landUseCode));
    }
    this.addFact(this.texts.text('panel.area'), formatArea(detail.areaSquareMetres, this.texts));
    this.viewerLink.href = detail.cadastreViewerUrl;
    this.viewerLink.hidden = false;
  }

  private renderMessage(text: string): void {
    this.title.textContent = '';
    this.title.hidden = true;
    this.facts.replaceChildren();
    this.viewerLink.hidden = true;
    this.message.textContent = text;
  }

  private setHidden(hidden: boolean): void {
    if (this.root.hidden === hidden) return;
    this.root.hidden = hidden;
    this.onVisibilityChange();
  }

  private addFact(label: string, value: string): void {
    const term = document.createElement('dt');
    term.textContent = label;
    const description = document.createElement('dd');
    description.textContent = value;
    this.facts.append(term, description);
  }
}
