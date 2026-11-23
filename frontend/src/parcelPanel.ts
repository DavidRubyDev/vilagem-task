import { landTypeName } from './landTypes';
import { landUseName } from './landUses';
import type { ParcelDetail } from './parcelDetail';
import { formatArea, formatCadastralArea, plotTypeName } from './parcelFormat';
import { TEXT } from './text';

/** The side panel with the details of the selected parcel. It overlays the map and never resizes it. */
export class ParcelPanel {
  private readonly root: HTMLElement;
  private readonly title: HTMLElement;
  private readonly facts: HTMLElement;
  private readonly viewerLink: HTMLAnchorElement;
  private readonly message: HTMLElement;

  constructor(onClose: () => void) {
    this.root = document.createElement('aside');
    this.root.className = 'parcel-panel';
    this.root.setAttribute('aria-label', TEXT.panelLabel);
    this.root.hidden = true;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'parcel-panel-close';
    close.setAttribute('aria-label', TEXT.closeLabel);
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
    this.viewerLink.textContent = TEXT.viewerLink;
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
    this.showMessageOnly(TEXT.loading);
  }

  showNotFound(): void {
    this.showMessageOnly(TEXT.notFound);
  }

  showError(): void {
    this.showMessageOnly(TEXT.error);
  }

  showDetail(detail: ParcelDetail): void {
    this.title.textContent = TEXT.heading(detail.parcelNumber);
    this.title.hidden = false;
    this.message.textContent = '';
    this.facts.replaceChildren();
    this.addFact(TEXT.cadastralArea, formatCadastralArea(detail.cadastralArea.name, detail.cadastralArea.code));
    this.addFact(TEXT.municipality, detail.municipality.name);
    this.addFact(TEXT.plotType, plotTypeName(detail.kind));
    this.addFact(TEXT.landType, landTypeName(detail.landTypeCode));
    if (detail.landUseCode !== null) {
      this.addFact(TEXT.landUse, landUseName(detail.landUseCode));
    }
    this.addFact(TEXT.area, formatArea(detail.areaSquareMetres));
    this.viewerLink.href = detail.cadastreViewerUrl;
    this.viewerLink.hidden = false;
    this.root.hidden = false;
  }

  hide(): void {
    this.root.hidden = true;
  }

  private showMessageOnly(text: string): void {
    this.title.textContent = '';
    this.title.hidden = true;
    this.facts.replaceChildren();
    this.viewerLink.hidden = true;
    this.message.textContent = text;
    this.root.hidden = false;
  }

  private addFact(label: string, value: string): void {
    const term = document.createElement('dt');
    term.textContent = label;
    const description = document.createElement('dd');
    description.textContent = value;
    this.facts.append(term, description);
  }
}
