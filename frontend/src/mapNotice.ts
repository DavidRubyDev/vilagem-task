import type { Texts } from './texts';

type NoticeKey = 'notice.parcelsMissing' | 'notice.detailsMissing';

export class MapNotice {
  private readonly element: HTMLElement;

  constructor(
    private readonly key: NoticeKey,
    texts: Texts,
  ) {
    this.element = document.createElement('p');
    this.element.className = 'map-notice';
    this.element.setAttribute('role', 'status');
    document.body.append(this.element);
    this.setTexts(texts);
  }

  setTexts(texts: Texts): void {
    this.element.textContent = texts.text(this.key);
  }
}
