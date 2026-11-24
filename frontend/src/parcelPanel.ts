import type { ParcelDetail } from './parcelDetail';
import { formatArea, formatCadastralArea, plotTypeName } from './parcelFormat';
import type { Texts } from './texts';

/** The side panel with the details of the selected parcel. It overlays the map and never resizes it. */
export class ParcelPanel {
  private readonly root: HTMLElement;
  private readonly title: HTMLElement;
  private readonly facts: HTMLElement;
  private readonly viewerLink: HTMLAnchorElement;
  private readonly message: HTMLElement;

  constructor(
    private readonly texts: Texts,
    onClose: () => void,
    private readonly onVisibilityChange: () => void,
  ) {
    this.root = document.createElement('aside');
    this.root.className = 'parcel-panel';
    this.root.setAttribute('aria-label', texts.text('panel.label'));
    this.root.hidden = true;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'parcel-panel-close';
    close.setAttribute('aria-label', texts.text('panel.close'));
    close.textContent = '×';
    close.addEventListener('click', onClose);

    this.title = document.createElement('h2');
    this.title.className = 'parcel-panel-title';
    this.facts = document.createElement('dl');
    this.facts.className = 'parcel-panel-facts';
    this.viewerLink = document.createElement('a');
    this.viewerLink.className = 'parcel-panel-viewer';
    this.viewerLink.target = '_blank';
    this.viewerLink.rel = 'noopener noreferrer';
    this.viewerLink.textContent = texts.text('panel.viewerLink');
    this.viewerLink.hidden = true;
    this.message = document.createElement('p');
    this.message.className = 'parcel-panel-message';
    this.message.setAttribute('role', 'status');

    this.root.append(close, this.title, this.facts, this.viewerLink, this.message);
    document.body.append(this.root);
  }

  element(): HTMLElement {
    return this.root;
  }

  isOpen(): boolean {
    return !this.root.hidden;
  }

  showLoading(): void {
    this.showMessageOnly(this.texts.text('panel.loading'));
  }

  showNotFound(): void {
    this.showMessageOnly(this.texts.text('panel.notFound'));
  }

  showError(): void {
    this.showMessageOnly(this.texts.text('panel.error'));
  }

  showDetail(detail: ParcelDetail): void {
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
    this.setHidden(false);
  }

  hide(): void {
    this.setHidden(true);
  }

  private showMessageOnly(text: string): void {
    this.title.textContent = '';
    this.title.hidden = true;
    this.facts.replaceChildren();
    this.viewerLink.hidden = true;
    this.message.textContent = text;
    this.setHidden(false);
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
